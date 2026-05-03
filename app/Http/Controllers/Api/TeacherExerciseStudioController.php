<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateTeacherExercisesRequest;
use App\Http\Requests\PublishTeacherExerciseRequest;
use App\Http\Requests\RegenerateTeacherExerciseItemRequest;
use App\Http\Requests\SaveTeacherExerciseDraftRequest;
use App\Http\Requests\UploadTeacherExerciseImageRequest;
use App\Jobs\GenerateTeacherExercisesJob;
use App\Models\ExerciseDocument;
use App\Models\ExerciseGenerationLog;
use App\Models\ExerciseItem;
use App\Services\TeacherExerciseAiService;
use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TeacherExerciseStudioController extends Controller
{
    public function generate(GenerateTeacherExercisesRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $document = null;
        if (!empty($data['document_id'])) {
            $document = ExerciseDocument::query()->findOrFail((int) $data['document_id']);
            $this->authorizeDocument($document, (int) $user->id);
        } else {
            $document = ExerciseDocument::query()->create([
                'user_id' => $user->id,
                'title' => (string) ($data['topic'] ?? 'Untitled Document'),
                'status' => 'draft',
                'language' => (string) ($data['language'] ?? 'en'),
                'meta' => [
                    'grade' => $data['grade'] ?? null,
                    'difficulty' => $data['difficulty'] ?? null,
                ],
            ]);
        }

        $prompt = $this->composePrompt((array) $data);

        $log = ExerciseGenerationLog::query()->create([
            'user_id' => $user->id,
            'document_id' => $document->id,
            'status' => 'processing',
            'prompt' => $prompt,
            'request_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'started_at' => now(),
        ]);

        GenerateTeacherExercisesJob::dispatchSync($log->id);

        return response()->json([
            'generation_id' => $log->id,
            'document_id' => $document->id,
            'status' => 'processing',
        ], 202);
    }

    public function generationStatus(ExerciseGenerationLog $generationLog, Request $request): JsonResponse
    {
        $this->authorizeLog($generationLog, (int) optional($request->user())->id);
        $generationLog->load(['document.items']);

        return response()->json([
            'generation' => [
                'id' => $generationLog->id,
                'status' => $generationLog->status,
                'error' => $generationLog->error,
                'started_at' => optional($generationLog->started_at)?->toIso8601String(),
                'finished_at' => optional($generationLog->finished_at)?->toIso8601String(),
            ],
            'document' => $generationLog->document
                ? $this->serializeDocument($generationLog->document->fresh('items'))
                : null,
            'parsed' => $this->decodeJson((string) ($generationLog->parsed_json ?? '')),
        ]);
    }

    public function show(ExerciseDocument $document, Request $request): JsonResponse
    {
        $this->authorizeDocument($document, (int) optional($request->user())->id);
        $document->load(['items', 'generationLogs' => function ($query) {
            $query->limit(10);
        }]);

        return response()->json([
            'document' => $this->serializeDocument($document),
            'generations' => $document->generationLogs->map(fn (ExerciseGenerationLog $log) => [
                'id' => $log->id,
                'status' => $log->status,
                'error' => $log->error,
                'started_at' => optional($log->started_at)?->toIso8601String(),
                'finished_at' => optional($log->finished_at)?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function saveDraft(SaveTeacherExerciseDraftRequest $request): JsonResponse
    {
        $userId = (int) optional($request->user())->id;
        $data = $request->validated();

        $document = null;
        if (!empty($data['document_id'])) {
            $document = ExerciseDocument::query()->findOrFail((int) $data['document_id']);
            $this->authorizeDocument($document, $userId);
        }

        $contentJson = isset($data['content_json'])
            ? json_encode($data['content_json'], JSON_UNESCAPED_UNICODE)
            : null;

        if (!$document) {
            $document = ExerciseDocument::query()->create([
                'user_id' => $userId,
                'title' => (string) $data['title'],
                'status' => 'draft',
                'content_json' => $contentJson,
                'content_html' => $data['content_html'] ?? null,
                'language' => (string) ($data['language'] ?? 'en'),
                'meta' => $data['meta'] ?? null,
            ]);
        } else {
            $document->update([
                'title' => (string) $data['title'],
                'status' => 'draft',
                'content_json' => $contentJson ?? $document->content_json,
                'content_html' => $data['content_html'] ?? $document->content_html,
                'language' => (string) ($data['language'] ?? $document->language),
                'meta' => $data['meta'] ?? $document->meta,
            ]);
        }

        if (isset($data['items']) && is_array($data['items'])) {
            $this->syncItems($document, $data['items']);
        }

        return response()->json([
            'document' => $this->serializeDocument($document->fresh('items')),
        ]);
    }

    public function publish(PublishTeacherExerciseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userId = (int) optional($request->user())->id;

        $document = ExerciseDocument::query()->findOrFail((int) $data['document_id']);
        $this->authorizeDocument($document, $userId);

        $contentJson = isset($data['content_json'])
            ? json_encode($data['content_json'], JSON_UNESCAPED_UNICODE)
            : $document->content_json;

        $document->update([
            'title' => (string) ($data['title'] ?? $document->title),
            'language' => (string) ($data['language'] ?? $document->language),
            'content_json' => $contentJson,
            'content_html' => $data['content_html'] ?? $document->content_html,
            'meta' => $data['meta'] ?? $document->meta,
            'status' => 'published',
            'published_at' => now(),
        ]);

        return response()->json([
            'document' => $this->serializeDocument($document->fresh('items')),
            'message' => __('Document published successfully.'),
        ]);
    }

    public function uploadImage(UploadTeacherExerciseImageRequest $request): JsonResponse
    {
        $userId = (int) optional($request->user())->id;
        $file = $request->file('image');

        $directory = 'teacher-exercises/' . max(1, $userId) . '/images';
        $path = $file->store($directory, 'public');

        return response()->json([
            'url' => Storage::disk('public')->url($path),
            'path' => $path,
            'name' => $file->getClientOriginalName(),
        ], 201);
    }

    public function pdf(Request $request)
    {
        $userId = (int) optional($request->user())->id;
        $data = $request->validate([
            'document_id' => 'nullable|integer|exists:exercise_documents,id',
            'title' => 'nullable|string|max:180',
            'content_html' => 'nullable|string',
            'language' => 'nullable|string|in:en,hy,fr,ru',
            'show_answers' => 'nullable|boolean',
        ]);
        $showAnswers = (bool) ($data['show_answers'] ?? true);

        $document = null;
        if (!empty($data['document_id'])) {
            $document = ExerciseDocument::query()->findOrFail((int) $data['document_id']);
            $this->authorizeDocument($document, $userId);
        }

        $title = trim((string) ($data['title'] ?? ($document?->title ?? 'Exercise Document')));
        if ($title === '') {
            $title = 'Exercise Document';
        }

        if ($document) {
            $document->loadMissing('items');
        }

        $contentHtml = trim((string) ($data['content_html'] ?? ''));
        if ($contentHtml === '') {
            $contentHtml = trim((string) ($document?->content_html ?? ''));
        }

        if (!$showAnswers && $contentHtml !== '') {
            // Keep edited document content, remove answer/explanation blocks only.
            $contentHtml = $this->stripAnswersFromHtml($contentHtml);
        } elseif (!$showAnswers && $document) {
            // Fallback when only DB content exists.
            $contentHtml = $this->buildHtmlFromItems($document, false);
        } elseif ($contentHtml === '' && $document) {
            $contentHtml = $this->buildHtmlFromItems($document, true);
        }

        if ($contentHtml === '') {
            return response()->json([
                'message' => __('Nothing to export.'),
            ], 422);
        }

        $contentHtml = $this->normalizePdfHtmlForEnvironment($contentHtml);
        $contentHtml = $this->renderLatexForPdf($contentHtml);

        if (!class_exists('\\Barryvdh\\DomPDF\\Facade\\Pdf')) {
            return response()->json([
                'message' => __('PDF renderer is not installed.'),
                'details' => __('Run: composer require barryvdh/laravel-dompdf'),
            ], 500);
        }

        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.teacher-exercises', [
                'title' => $title,
                'contentHtml' => $contentHtml,
                'language' => (string) ($data['language'] ?? ($document?->language ?? 'en')),
            ])
                ->setPaper('a4')
                ->setOption('defaultFont', 'DejaVu Sans');

            return $pdf->download($this->safePdfFilename($title));
        } catch (Throwable $e) {
            if (str_contains(mb_strtolower($e->getMessage()), 'gd extension is required')) {
                return response()->json([
                    'message' => __('PDF export requires GD for regular images. Geometry SVG drawings are supported without GD.'),
                ], 422);
            }

            throw $e;
        }
    }

    public function regenerateItem(
        RegenerateTeacherExerciseItemRequest $request,
        TeacherExerciseAiService $aiService
    ): JsonResponse {
        $data = $request->validated();
        $userId = (int) optional($request->user())->id;

        $document = ExerciseDocument::query()->findOrFail((int) $data['document_id']);
        $this->authorizeDocument($document, $userId);

        $item = ExerciseItem::query()
            ->where('document_id', $document->id)
            ->findOrFail((int) $data['item_id']);

        try {
            $result = $aiService->generate([
                'topic' => (string) ($data['topic'] ?? $document->title),
                'grade' => $document->meta['grade'] ?? null,
                'difficulty' => (string) ($data['difficulty'] ?? $item->difficulty ?? 'medium'),
                'count' => 1,
                'language' => (string) ($data['language'] ?? $document->language ?? 'en'),
                'instructions' => trim((string) ($data['instructions'] ?? '')) !== ''
                    ? (string) $data['instructions']
                    : 'Regenerate one question similar to: ' . $item->question,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => __('Failed to regenerate item. Please try again.'),
                'details' => $e->getMessage(),
            ], 502);
        }

        $newItem = $result['items'][0] ?? null;
        if (!$newItem) {
            return response()->json([
                'message' => __('Failed to regenerate item.'),
            ], 422);
        }

        $item->update([
            'type' => (string) ($newItem['type'] ?? 'mcq'),
            'question' => (string) ($newItem['question_html'] ?? $newItem['question'] ?? $item->question),
            'options' => is_array($newItem['options_html'] ?? null)
                ? $newItem['options_html']
                : ($newItem['options'] ?? $item->options),
            'answer' => (string) ($newItem['answer_html'] ?? $newItem['answer'] ?? $item->answer),
            'explanation' => $newItem['explanation_html'] ?? $newItem['explanation'] ?? $item->explanation,
            'difficulty' => $newItem['difficulty'] ?? $item->difficulty,
            'points' => (int) ($newItem['points'] ?? $item->points),
            'source' => 'ai',
        ]);

        return response()->json([
            'item' => $item->fresh(),
            'raw' => $result['raw'] ?? null,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function syncItems(ExerciseDocument $document, array $items): void
    {
        DB::transaction(function () use ($document, $items) {
            $document->items()->delete();

            $order = 1;
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $question = trim((string) ($item['question_html'] ?? $item['question'] ?? ''));
                if ($question === '') {
                    continue;
                }

                $currentOrder = isset($item['item_order'])
                    ? max(1, (int) $item['item_order'])
                    : $order;
                $order = $currentOrder + 1;

                ExerciseItem::query()->create([
                    'document_id' => $document->id,
                    'item_order' => $currentOrder,
                    'type' => (string) ($item['type'] ?? 'mcq'),
                    'question' => $question,
                    'options' => is_array($item['options_html'] ?? null)
                        ? $item['options_html']
                        : (is_array($item['options'] ?? null) ? $item['options'] : []),
                    'answer' => (string) ($item['answer_html'] ?? $item['answer'] ?? ''),
                    'explanation' => $item['explanation_html'] ?? $item['explanation'] ?? null,
                    'difficulty' => $item['difficulty'] ?? null,
                    'points' => max(1, (int) ($item['points'] ?? 1)),
                    'source' => (string) ($item['source'] ?? 'manual'),
                ]);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDocument(ExerciseDocument $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'status' => $document->status,
            'language' => $document->language,
            'meta' => $document->meta,
            'content_json' => $this->decodeJson((string) ($document->content_json ?? '')),
            'content_html' => $document->content_html,
            'generated_at' => optional($document->generated_at)?->toIso8601String(),
            'published_at' => optional($document->published_at)?->toIso8601String(),
            'items' => $document->items->map(fn (ExerciseItem $item) => [
                'id' => $item->id,
                'item_order' => $item->item_order,
                'type' => $item->type,
                'question' => $item->question,
                'question_html' => $item->question,
                'options' => $item->options ?? [],
                'options_html' => $item->options ?? [],
                'answer' => $item->answer,
                'answer_html' => $item->answer,
                'explanation' => $item->explanation,
                'explanation_html' => $item->explanation,
                'difficulty' => $item->difficulty,
                'points' => $item->points,
                'source' => $item->source,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function composePrompt(array $data): string
    {
        return implode("\n", [
            'Topic: ' . (string) ($data['topic'] ?? ''),
            'Grade: ' . (string) ($data['grade'] ?? ''),
            'Difficulty: ' . (string) ($data['difficulty'] ?? ''),
            'Count: ' . (string) ($data['count'] ?? ''),
            'Language: ' . (string) ($data['language'] ?? ''),
            'Instructions: ' . (string) ($data['instructions'] ?? ''),
        ]);
    }

    private function authorizeDocument(ExerciseDocument $document, int $userId): void
    {
        if ($userId <= 0 || (int) $document->user_id !== $userId) {
            abort(403);
        }
    }

    private function authorizeLog(ExerciseGenerationLog $log, int $userId): void
    {
        if ($userId <= 0 || (int) $log->user_id !== $userId) {
            abort(403);
        }
    }

    private function safePdfFilename(string $title): string
    {
        $base = preg_replace('/[^A-Za-z0-9_\-]+/u', '_', $title) ?? '';
        $base = trim($base, '_');
        if ($base === '') {
            $base = 'teacher_exercises';
        }

        return $base . '.pdf';
    }

    private function buildHtmlFromItems(ExerciseDocument $document, bool $showAnswers = true): string
    {
        $items = $document->items()->orderBy('item_order')->get();
        if ($items->isEmpty()) {
            return '';
        }

        $html = '<h2>' . e($document->title) . '</h2>';
        foreach ($items as $item) {
            $html .= '<h3>' . e(__('Exercise')) . ' ' . (int) $item->item_order . '</h3>';
            $html .= $this->renderBlockHtml((string) $item->question);

            $options = is_array($item->options) ? $item->options : [];
            if (!empty($options)) {
                $html .= '<ol>';
                foreach ($options as $option) {
                    $html .= '<li>' . $this->renderInlineHtml((string) $option) . '</li>';
                }
                $html .= '</ol>';
            }

            if ($showAnswers) {
                $html .= '<p><strong>' . e(__('Answer')) . ':</strong> ' . $this->renderInlineHtml((string) $item->answer) . '</p>';
            }
            if ($showAnswers && !empty($item->explanation)) {
                $html .= '<p><strong>' . e(__('Explanation')) . ':</strong> ' . $this->renderInlineHtml((string) $item->explanation) . '</p>';
            }
        }

        return $this->renderLatexForPdf($html);
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

    private function stripAnswersFromHtml(string $html): string
    {
        $patterns = [
            '/<p[^>]*>\s*(?:<strong[^>]*>)?\s*(?:Answer|Explanation|Պատասխան|Բացատրություն|Réponse|Explication|Ответ|Объяснение)\s*:.*?<\/p>/isu',
            '/<li[^>]*>\s*(?:Answer|Explanation|Պատասխան|Բացատրություն|Réponse|Explication|Ответ|Объяснение)\s*:.*?<\/li>/isu',
        ];

        return trim(preg_replace($patterns, '', $html) ?? $html);
    }

    private function renderLatexForPdf(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $replace = function (array $match): string {
            $latex = (string) ($match[1] ?? '');
            $text = $this->latexToReadableText($latex);
            if ($text === '') {
                return '';
            }

            return '<span class="math-inline">' . e($text) . '</span>';
        };

        // Display math first.
        $html = preg_replace_callback('/\$\$([\s\S]+?)\$\$/u', $replace, $html) ?? $html;
        $html = preg_replace_callback('/\\\\\[(.+?)\\\\\]/u', $replace, $html) ?? $html;

        // Inline math.
        $html = preg_replace_callback('/\\\\\((.+?)\\\\\)/u', $replace, $html) ?? $html;
        $html = preg_replace_callback('/(?<!\$)\$([^$\n]+?)\$(?!\$)/u', $replace, $html) ?? $html;

        // Ensure no raw dollar delimiters are visible in exported PDF.
        $html = str_replace('$', '', $html);

        return $html;
    }

    private function normalizePdfHtmlForEnvironment(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        if (extension_loaded('gd')) {
            return $html;
        }

        // Without GD, DomPDF fails on raster images (<img ...>).
        // We keep geometry drawings by decoding their embedded SVG payload
        // and turning them into inline <svg> markup.
        $html = preg_replace_callback(
            '/<img\b[^>]*data-geometry-drawing[^>]*>/iu',
            function (array $match): string {
                $tag = (string) ($match[0] ?? '');
                $svg = $this->extractSvgFromGeometryImageTag($tag);

                if ($svg !== null) {
                    return $this->sanitizeInlineSvgForPdf($svg);
                }

                return '<div style="border:1px solid #cbd5e1;padding:8px;margin:8px 0;font-size:11px;color:#64748b;">'
                    . e(__('Geometry drawing'))
                    . '</div>';
            },
            $html
        ) ?? $html;

        // Remove remaining regular images in no-GD environments
        // to avoid runtime 500 exceptions.
        $html = preg_replace('/<img\b[^>]*>/iu', '', $html) ?? $html;

        return $html;
    }

    private function extractSvgFromGeometryImageTag(string $tag): ?string
    {
        if (preg_match('/data-geometry-svg=(["\'])(.*?)\1/iu', $tag, $m) === 1) {
            $encoded = html_entity_decode((string) ($m[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($encoded !== '') {
                $decoded = base64_decode($encoded, true);
                if ($decoded !== false && str_contains($decoded, '<svg')) {
                    return $decoded;
                }
            }
        }

        if (preg_match('/src=(["\'])data:image\/svg\+xml;base64,([^"\']+)\1/iu', $tag, $m) === 1) {
            $encoded = html_entity_decode((string) ($m[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($encoded !== '') {
                $decoded = base64_decode($encoded, true);
                if ($decoded !== false && str_contains($decoded, '<svg')) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    private function sanitizeInlineSvgForPdf(string $svg): string
    {
        $safe = trim($svg);
        if ($safe === '') {
            return '';
        }

        $safe = preg_replace('/<script\b[^>]*>.*?<\/script>/isu', '', $safe) ?? $safe;
        $safe = preg_replace('/\s+on[a-z]+\s*=\s*(["\']).*?\1/isu', '', $safe) ?? $safe;

        if (!preg_match('/<svg\b[^>]*xmlns=/iu', $safe)) {
            $safe = preg_replace('/<svg\b/iu', '<svg xmlns="http://www.w3.org/2000/svg"', $safe, 1) ?? $safe;
        }

        return $safe;
    }

    private function latexToReadableText(string $latex): string
    {
        $value = trim($latex);
        if ($value === '') {
            return '';
        }

        // Remove common style directives.
        $value = preg_replace('/\\\\(?:displaystyle|textstyle|scriptstyle|scriptscriptstyle)\b/u', '', $value) ?? $value;

        // \text{...}
        for ($i = 0; $i < 10; $i++) {
            $next = preg_replace('/\\\\text\{([^{}]*)\}/u', '$1', $value, -1, $count);
            $value = $next ?? $value;
            if (($count ?? 0) === 0) {
                break;
            }
        }

        // \frac{a}{b}
        for ($i = 0; $i < 10; $i++) {
            $next = preg_replace('/\\\\frac\{([^{}]+)\}\{([^{}]+)\}/u', '($1/$2)', $value, -1, $count);
            $value = $next ?? $value;
            if (($count ?? 0) === 0) {
                break;
            }
        }

        // \sqrt{x}
        for ($i = 0; $i < 10; $i++) {
            $next = preg_replace('/\\\\sqrt\{([^{}]+)\}/u', 'sqrt($1)', $value, -1, $count);
            $value = $next ?? $value;
            if (($count ?? 0) === 0) {
                break;
            }
        }

        $replacements = [
            '\cdot' => '·',
            '\times' => '×',
            '\div' => '÷',
            '\pm' => '±',
            '\mp' => '∓',
            '\leq' => '≤',
            '\geq' => '≥',
            '\neq' => '≠',
            '\approx' => '≈',
            '\infty' => '∞',
            '\pi' => 'π',
            '\alpha' => 'α',
            '\beta' => 'β',
            '\gamma' => 'γ',
            '\theta' => 'θ',
            '\Delta' => 'Δ',
            '\sin' => 'sin',
            '\cos' => 'cos',
            '\tan' => 'tan',
            '\log' => 'log',
            '\ln' => 'ln',
            '\left' => '',
            '\right' => '',
            '\,' => ' ',
            '\;' => ' ',
            '\:' => ' ',
            '\!' => '',
            '\\\\' => ' / ',
        ];

        $value = strtr($value, $replacements);
        $value = str_replace(['{', '}'], '', $value);
        $value = str_replace('\\', '', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
