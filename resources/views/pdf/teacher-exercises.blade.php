<!DOCTYPE html>
<html lang="{{ $language ?? app()->getLocale() }}">
  <head>
    <meta charset="utf-8" />
    <style>
      @page {
        margin: 28px 30px;
      }
      body {
        font-family: "DejaVu Sans", sans-serif;
        font-size: 12px;
        line-height: 1.5;
        color: #0f172a;
      }
      h1 {
        margin: 0 0 14px 0;
        font-size: 22px;
      }
      h2 {
        margin: 12px 0 8px 0;
        font-size: 18px;
      }
      h3 {
        margin: 10px 0 6px 0;
        font-size: 15px;
      }
      p {
        margin: 6px 0;
        white-space: pre-wrap;
      }
      ol, ul {
        margin: 6px 0 10px 24px;
      }
      img {
        max-width: 100%;
        height: auto;
      }
      .math-inline {
        font-family: "DejaVu Sans", sans-serif;
        font-style: italic;
      }
      .doc {
        width: 100%;
      }
    </style>
  </head>
  <body>
    <h1>{{ $title }}</h1>
    <div class="doc">{!! $contentHtml !!}</div>
  </body>
</html>
