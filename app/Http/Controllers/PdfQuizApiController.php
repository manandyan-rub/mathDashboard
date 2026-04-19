<?php

namespace App\Http\Controllers;

use App\Models\PdfImport;
use App\Models\PdfQuiz;
use App\Models\PdfQuizAttempt;
use App\Models\PdfQuizAttemptAnswer;
use App\Models\PdfQuizQuestion;
use App\Models\PdfQuizQuestionOption;
use App\Services\PdfQuizGeneratorService;
use App\Services\PdfTextExtractorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PdfQuizApiController extends Controller
{
    public function store(
        Request $request,
        PdfTextExtractorService $extractor,
        PdfQuizGeneratorService $generator
    ) {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => __('Unauthorized')], 401);
        }

        $data = $request->validate([
            'pdf' => ['required','file', 'mimes:pdf', 'max:20480'],
            'title' => ['nullable', 'string', 'max:120'],
            'question_count' =>[ 'nullable','integer ',' min:1','max:30'],
            'difficulty' =>[ 'nullable','string','max:40'],
            'language' =>[ 'nullable','string','max:40'],
        ]);

        $file = $request->file('pdf');
        $originalName = $file->getClientOriginalName();
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = pathinfo($originalName, PATHINFO_FILENAME) ?: 'PDF Quiz';
        }

        $path = $file->store('pdf-imports');

        $import = PdfImport::create([
            'user_id' => $user->id,
            'title' => $title,
            'file_path' => $path,
            'original_name' => $originalName,
            'status' => 'processing',
        ]);

        try {
            $text = $extractor->extract(storage_path('app/' . $path));
            if (trim($text) === '') {
                $import->update(['status' => 'failed', 'extracted_text' => '']);
                return response()->json([
                    'message' => __('Unable to extract text from PDF.'),
                ], 422);
            }

            $import->update([
                'extracted_text' => $text,
            ]);

            $questions = $generator->generate($text, [
                'count' => (int) ($data['question_count'] ?? 10),
                'difficulty' => (string) ($data['difficulty'] ?? 'medium'),
                'language' => (string) ($data['language'] ?? 'armenian'),
            ]);

            if (empty($questions)) {
                $import->update(['status' => 'failed']);
                return response()->json([
                    'message' => __('Failed to generate questions.'),
                ], 422);
            }

            $quiz = DB::transaction(function () use ($import, $questions, $data) {
                $quiz = PdfQuiz::create([
                    'pdf_import_id' => $import->id,
                    'title' => $import->title,
                    'description' => null,
                    'difficulty' => (string) ($data['difficulty'] ?? 'medium'),
                    'total_questions' => count($questions),
                ]);

                foreach ($questions as $index => $question) {
                    $correctIndex = (int) $question['correct_index'];
                    $options = $question['options'];
                    $correctAnswer = $options[$correctIndex] ?? null;

                    $questionRow = PdfQuizQuestion::create([
                        'quiz_id' => $quiz->id,
                        'question' => $question['question'],
                        'explanation' => $question['explanation'] ?: null,
                        'correct_answer' => $correctAnswer,
                        'order' => $index + 1,
                    ]);

                    foreach ($options as $optIndex => $optionText) {
                        PdfQuizQuestionOption::create([
                            'question_id' => $questionRow->id,
                            'option_text' => $optionText,
                            'is_correct' => $optIndex === $correctIndex,
                            'order' => $optIndex + 1,
                        ]);
                    }
                }

                return $quiz;
            });

            $import->update(['status' => 'completed']);

            return response()->json([
                'quiz_id' => $quiz->id,
                'total_questions' => $quiz->total_questions,
            ]);
        } catch (\Throwable $e) {
            $import->update(['status' => 'failed']);

            return response()->json([
                'message' => __('PDF quiz generation failed.'),
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function showQuiz(PdfQuiz $quiz)
    {
        $this->authorizeQuiz($quiz);

        $quiz->load(['questions.options']);

        return response()->json([
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'difficulty' => $quiz->difficulty,
                'total_questions' => $quiz->total_questions,
            ],
            'questions' => $quiz->questions->map(function ($question) {
                return [
                    'id' => $question->id,
                    'question' => $question->question,
                    'explanation' => $question->explanation,
                    'options' => $question->options->map(function ($option) {
                        return [
                            'id' => $option->id,
                            'text' => $option->option_text,
                        ];
                    })->values(),
                ];
            })->values(),
        ]);
    }

    public function startAttempt(PdfQuiz $quiz)
    {
        $this->authorizeQuiz($quiz);

        $attempt = PdfQuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => request()->user()->id,
            'total_questions' => $quiz->total_questions,
            'started_at' => now(),
        ]);

        return response()->json([
            'attempt_id' => $attempt->id,
        ]);
    }

    public function submitAttempt(Request $request, PdfQuizAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);

        if ($attempt->finished_at) {
            return response()->json(['message' => __('Attempt is already finished.')], 422);
        }

        $data = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.option_id' => 'nullable|integer',
        ]);

        $quiz = $attempt->quiz()->with(['questions.options'])->firstOrFail();
        $questionMap = $quiz->questions->keyBy('id');

        $answersByQuestion = collect($data['answers'])->keyBy('question_id');

        $correct = 0;
        $total = $quiz->questions->count();

        DB::transaction(function () use ($attempt, $quiz, $questionMap, $answersByQuestion, &$correct) {
            $attempt->answers()->delete();

            foreach ($quiz->questions as $question) {
                $answerPayload = $answersByQuestion->get($question->id);
                $selectedOptionId = $answerPayload['option_id'] ?? null;
                $selectedOption = $question->options->firstWhere('id', $selectedOptionId);
                $isCorrect = $selectedOption?->is_correct ?? false;

                if ($isCorrect) {
                    $correct++;
                }

                PdfQuizAttemptAnswer::create([
                    'attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                    'selected_option_id' => $selectedOptionId,
                    'is_correct' => $isCorrect,
                ]);
            }
        });

        $wrong = max(0, $total - $correct);
        $score = $total > 0 ? (int) round(($correct / $total) * 100) : 0;

        $attempt->update([
            'score' => $score,
            'correct_answers' => $correct,
            'wrong_answers' => $wrong,
            'finished_at' => now(),
        ]);

        return response()->json([
            'attempt_id' => $attempt->id,
            'score' => $score,
            'correct_answers' => $correct,
            'wrong_answers' => $wrong,
            'total_questions' => $total,
        ]);
    }

    public function showAttempt(PdfQuizAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);

        $attempt->load(['answers.question.options', 'answers.selectedOption', 'quiz']);

        return response()->json([
            'attempt' => [
                'id' => $attempt->id,
                'score' => $attempt->score,
                'correct_answers' => $attempt->correct_answers,
                'wrong_answers' => $attempt->wrong_answers,
                'total_questions' => $attempt->total_questions,
                'finished_at' => optional($attempt->finished_at)->toIso8601String(),
                'quiz_title' => $attempt->quiz->title,
            ],
            'answers' => $attempt->answers->map(function ($answer) {
                $question = $answer->question;
                return [
                    'question_id' => $question->id,
                    'question' => $question->question,
                    'explanation' => $question->explanation,
                    'selected_option_id' => $answer->selected_option_id,
                    'is_correct' => $answer->is_correct,
                    'options' => $question->options->map(function ($option) use ($answer) {
                        return [
                            'id' => $option->id,
                            'text' => $option->option_text,
                            'is_correct' => $option->is_correct,
                            'is_selected' => $option->id === $answer->selected_option_id,
                        ];
                    })->values(),
                ];
            })->values(),
        ]);
    }

    private function authorizeQuiz(PdfQuiz $quiz): void
    {
        $userId = optional(request()->user())->id;
        if (!$userId || $quiz->import?->user_id !== $userId) {
            abort(403);
        }
    }

    private function authorizeAttempt(PdfQuizAttempt $attempt): void
    {
        $userId = optional(request()->user())->id;
        if (!$userId || $attempt->user_id !== $userId) {
            abort(403);
        }
    }
}
