<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>Events in {{ config('app.name') }}</title>
</head>
<body>
<div>
  <div class="mermaid">
    {{ config('laravel-event-visualizer.theme.diagram_type', 'flowchart LR') }}
    {{ $events }}
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/mermaid/9.0.1/mermaid.min.js" integrity="sha512-epOjc4LHlbK/a3+OjKdQMnoVZ5u6JYkw7AboRUqUV4dLULCLdcf4wt5TXuSYjpBJF+odA830p3rXbBniex33Bg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>mermaid.initialize({startOnLoad:true});</script>
</body>
</html>
