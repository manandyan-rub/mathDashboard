<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMathWorkspaceCalculationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MathWorkspaceController extends Controller
{
    public function index(Request $request): Response
    {
        $savedCalculations = $request->user()
            ->mathWorkspaceCalculations()
            ->latest()
            ->limit(12)
            ->get([
                'id',
                'title',
                'topic',
                'problem_text',
                'state',
                'created_at',
            ]);

        return Inertia::render('Mathematics/Workspace', [
            'savedCalculations' => $savedCalculations,
        ]);
    }

    public function store(
        StoreMathWorkspaceCalculationRequest $request,
    ): RedirectResponse {
        $request->user()->mathWorkspaceCalculations()->create(
            $request->validated(),
        );

        return back()->with('success', __('Calculation saved.'));
    }
}
