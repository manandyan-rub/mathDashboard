<?php

namespace App\Jobs;

use App\Models\ExerciseDocument;
use App\Models\ExerciseGenerationLog;
use App\Models\ExerciseItem;
use App\Services\TeacherExerciseAiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class GenerateTeacherExercisesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $logId)
    {
    }

    public function handle(TeacherExerciseAiService $aiService): void
    {
        $log = ExerciseGenerationLog::query()->find($this->logId);
        if (!$log || $log->status !== 'processing') {
            return;
        }

        $requestData = json_decode((string) ($log->request_json ?? '{}'), true);
        if (!is_array($requestData)) {
            $requestData = [];
        }

        try {
            $generated = $aiService->generate($requestData);
            $newItems = $generated['items'] ?? [];
            $raw = (string) ($generated['raw'] ?? '');

            if (empty($newItems)) {
                throw new \RuntimeException('AI did not return any items.');
            }

            DB::transaction(function () use ($log, $requestData, $newItems, $raw) {
                $document = $this->resolveDocument($log, $requestData);
                $replace = (bool) ($requestData['replace_items'] ?? true);

                $existingItems = $document->items()->orderBy('item_order')->get()->toArray();
                if ($replace) {
                    $document->items()->delete();
                    $existingItems = [];
                }

                $merged = [];
                $nextOrder = 1;
                foreach ($existingItems as $existing) {
                    $merged[] = [
                        'item_order' => $nextOrder++,
                        'type' => (string) ($existing['type'] ?? 'mcq'),
                        'question' => (string) ($existing['question'] ?? ''),
                        'options' => $existing['options'] ?? [],
                        'answer' => (string) ($existing['answer'] ?? ''),
                        'explanation' => $existing['explanation'] ?? null,
                        'difficulty' => $existing['difficulty'] ?? null,
                        'points' => (int) ($existing['points'] ?? 1),
                        'source' => (string) ($existing['source'] ?? 'ai'),
                    ];
                }

                foreach ($newItems as $item) {
                    $merged[] = [
                        'item_order' => $nextOrder++,
                        'type' => (string) ($item['type'] ?? 'mcq'),
                        'question' => (string) ($item['question'] ?? ''),
                        'options' => $item['options'] ?? [],
                        'answer' => (string) ($item['answer'] ?? ''),
                        'explanation' => $item['explanation'] ?? null,
                        'difficulty' => $item['difficulty'] ?? null,
                        'points' => (int) ($item['points'] ?? 1),
                        'source' => (string) ($item['source'] ?? 'ai'),
                    ];
                }

                foreach ($merged as $item) {
                    ExerciseItem::query()->create([
                        'document_id' => $document->id,
                        'item_order' => $item['item_order'],
                        'type' => $item['type'],
                        'question' => $item['question'],
                        'options' => $item['options'],
                        'answer' => $item['answer'],
                        'explanation' => $item['explanation'],
                        'difficulty' => $item['difficulty'],
                        'points' => $item['points'],
                        'source' => $item['source'],
                    ]);
                }

                $content = $this->buildEditorDocument($document->title, $merged);

                $document->update([
                    'content_json' => json_encode($content, JSON_UNESCAPED_UNICODE),
                    'content_html' => $this->buildHtmlPreview($document->title, $merged),
                    'status' => 'draft',
                    'generated_at' => now(),
                    'language' => (string) ($requestData['language'] ?? $document->language ?? 'en'),
                ]);

                $log->update([
                    'document_id' => $document->id,
                    'status' => 'ready',
                    'response_raw' => $raw,
                    'parsed_json' => json_encode(['items' => $newItems], JSON_UNESCAPED_UNICODE),
                    'finished_at' => now(),
                    'error' => null,
                ]);
            });
        } catch (\Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $log = ExerciseGenerationLog::query()->find($this->logId);
        if ($log && $log->status !== 'ready') {
            $log->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $requestData
     */
    private function resolveDocument(ExerciseGenerationLog $log, array $requestData): ExerciseDocument
    {
        if ($log->document_id) {
            $existing = ExerciseDocument::query()->find($log->document_id);
            if ($existing) {
                return $existing;
            }
        }

        return ExerciseDocument::query()->create([
            'user_id' => $log->user_id,
            'title' => (string) ($requestData['topic'] ?? 'Exercise Document'),
            'status' => 'draft',
            'language' => (string) ($requestData['language'] ?? 'en'),
            'meta' => [
                'grade' => $requestData['grade'] ?? null,
                'difficulty' => $requestData['difficulty'] ?? null,
            ],
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function buildEditorDocument(string $title, array $items): array
    {
        $content = [
            [
                'type' => 'heading',
                'attrs' => ['level' => 2],
                'content' => [['type' => 'text', 'text' => $title]],
            ],
        ];

        foreach ($items as $item) {
            $content[] = [
                'type' => 'heading',
                'attrs' => ['level' => 3],
                'content' => [[
                    'type' => 'text',
                    'text' => 'Exercise ' . (string) ($item['item_order'] ?? 1),
                ]],
            ];
            $content[] = [
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => (string) ($item['question'] ?? ''),
                ]],
            ];

            $options = is_array($item['options'] ?? null) ? $item['options'] : [];
            if (!empty($options)) {
                $listItems = [];
                foreach ($options as $option) {
                    $listItems[] = [
                        'type' => 'listItem',
                        'content' => [[
                            'type' => 'paragraph',
                            'content' => [[
                                'type' => 'text',
                                'text' => (string) $option,
                            ]],
                        ]],
                    ];
                }
                $content[] = [
                    'type' => 'orderedList',
                    'attrs' => ['start' => 1],
                    'content' => $listItems,
                ];
            }

            $content[] = [
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => 'Answer: ' . (string) ($item['answer'] ?? ''),
                ]],
            ];

            if (!empty($item['explanation'])) {
                $content[] = [
                    'type' => 'paragraph',
                    'content' => [[
                        'type' => 'text',
                        'text' => 'Explanation: ' . (string) $item['explanation'],
                    ]],
                ];
            }
        }

        return [
            'type' => 'doc',
            'content' => $content,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function buildHtmlPreview(string $title, array $items): string
    {
        $html = '<h2>' . e($title) . '</h2>';
        foreach ($items as $item) {
            $html .= '<h3>Exercise ' . (int) ($item['item_order'] ?? 1) . '</h3>';
            $html .= $this->renderBlockHtml((string) ($item['question'] ?? ''));

            $options = is_array($item['options'] ?? null) ? $item['options'] : [];
            if (!empty($options)) {
                $html .= '<ol>';
                foreach ($options as $option) {
                    $html .= '<li>' . $this->renderInlineHtml((string) $option) . '</li>';
                }
                $html .= '</ol>';
            }

            $html .= '<p><strong>Answer:</strong> ' . $this->renderInlineHtml((string) ($item['answer'] ?? '')) . '</p>';
            if (!empty($item['explanation'])) {
                $html .= '<p><strong>Explanation:</strong> ' . $this->renderInlineHtml((string) $item['explanation']) . '</p>';
            }
        }

        return $html;
    }

    private function renderBlockHtml(string $value): string
    {
        $content = trim($value);
        if ($content === '') {
            return '<p></p>';
        }

        if ($this->looksLikeHtml($content)) {
            return $content;
        }

        return '<p>' . e($content) . '</p>';
    }

    private function renderInlineHtml(string $value): string
    {
        $content = trim($value);
        if ($content === '') {
            return '';
        }

        return $this->looksLikeHtml($content) ? $content : e($content);
    }

    private function looksLikeHtml(string $value): bool
    {
        return (bool) preg_match('/<[^>]+>/u', $value);
    }
}
