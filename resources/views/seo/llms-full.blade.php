{{-- llms.txt, then every public page in full, so an agent can read the whole site in one fetch. --}}
@include('seo.llms')

---

# Every page in full

@foreach (\App\Support\PageMarkdown::paths() as $path)
{!! \App\Support\PageMarkdown::for($path) !!}
---

@endforeach
