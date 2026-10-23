<x-layouts.app
    :title="$preview['title']"
    :description="$preview['description']"
    :og-image="$preview['og_image']"
    :og-image-alt="$preview['og_image_alt']"
    :labels="$preview['labels']"
    robots="noindex, nofollow"
    schema="none"
>
    <livewire:review-board :token="$token" />
</x-layouts.app>
