<?php

namespace App\Http\Controllers;

use App\Models\PdfQuiz;
use App\Models\PdfQuizAttempt;
use Inertia\Inertia;
use Inertia\Response;

class PdfQuizController extends Controller
{
    public function upload(): Response
    {
        return Inertia::render('PdfQuiz/Upload');
    }

    public function preview(PdfQuiz $quiz): Response
    {
        $this->authorizeQuiz($quiz);

        return Inertia::render('PdfQuiz/Preview', [
            'quizId' => $quiz->id,
        ]);
    }

    public function session(PdfQuizAttempt $attempt): Response
    {
        $this->authorizeAttempt($attempt);

        return Inertia::render('PdfQuiz/Session', [
            'quizId' => $attempt->quiz_id,
            'attemptId' => $attempt->id,
        ]);
    }

    public function result(PdfQuizAttempt $attempt): Response
    {
        $this->authorizeAttempt($attempt);

        return Inertia::render('PdfQuiz/Result', [
            'quizId' => $attempt->quiz_id,
            'attemptId' => $attempt->id,
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
