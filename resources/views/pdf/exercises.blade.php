<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
  <head>
    <meta charset="utf-8" />
    <style>
      @page {
        margin: 36px 40px;
      }
      body {
        font-family: "DejaVu Sans", sans-serif;
        font-size: 12px;
        color: #111827;
      }
      h1 {
        font-size: 20px;
        margin: 0 0 18px 0;
      }
      .exercise {
        margin: 0 0 18px 0;
        padding-bottom: 12px;
        border-bottom: 1px solid #e5e7eb;
        page-break-inside: avoid;
      }
      .label {
        font-weight: 700;
        margin-top: 6px;
      }
      .difficulty {
        color: #6b7280;
        font-size: 11px;
      }
      .text {
        white-space: pre-wrap;
      }
    </style>
  </head>
  <body>
    <h1>{{ $title }}</h1>

    @foreach ($exercises as $index => $exercise)
      <div class="exercise">
        <div class="label">{{ __('Exercise :number', ['number' => $index + 1]) }}</div>
        @if (!empty($exercise['difficulty']))
          <div class="difficulty">{{ __('Difficulty: :difficulty', ['difficulty' => $exercise['difficulty']]) }}</div>
        @endif
        <div class="label">{{ __('Question') }}</div>
        <div class="text">{{ $exercise['question'] }}</div>
        <div class="label">{{ __('Answer') }}</div>
        <div class="text">{{ $exercise['answer'] }}</div>
      </div>
    @endforeach
  </body>
</html>
