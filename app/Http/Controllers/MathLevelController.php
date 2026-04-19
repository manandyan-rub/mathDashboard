<?php

namespace App\Http\Controllers;

use App\Models\MathLevelAttempt;
use Inertia\Inertia;
use Inertia\Response;

class MathLevelController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('MathLevels/Index');
    }

    public function session(MathLevelAttempt $attempt): Response
    {
        $this->authorizeAttempt($attempt);

        return Inertia::render('MathLevels/Session', [
            'attemptId' => $attempt->id,
        ]);
    }

    public function result(MathLevelAttempt $attempt): Response
    {
        $this->authorizeAttempt($attempt);

        return Inertia::render('MathLevels/Result', [
            'attemptId' => $attempt->id,
        ]);
    }

    public function rewards(): Response
    {
        return Inertia::render('MathLevels/Rewards');
    }

    private function authorizeAttempt(MathLevelAttempt $attempt): void
    {
        $userId = optional(request()->user())->id;
        if (!$userId || $attempt->user_id !== $userId) {
            abort(403);
        }
    }
}
