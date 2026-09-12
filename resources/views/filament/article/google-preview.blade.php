{{-- Live SERP preview. Latin URL and Arabic title sit on the same line, so the
     URL is isolated or the whole line reorders around it. --}}
<div
    x-data="{
        title: $wire.$entangle('data.meta_title'),
        fallbackTitle: $wire.$entangle('data.title'),
        description: $wire.$entangle('data.meta_description'),
        slug: $wire.$entangle('data.slug'),
        locale: $wire.$entangle('data.locale'),
        get shownTitle() {
            const t = (this.title || this.fallbackTitle || 'عنوان المادة').trim()
            return t.length > 60 ? t.slice(0, 59) + '…' : t
        },
        get shownDescription() {
            const d = (this.description || 'أضف وصف ميتا ليظهر هنا كما سيراه القارئ في نتائج البحث.').trim()
            return d.length > 160 ? d.slice(0, 159) + '…' : d
        },
    }"
    class="fi-section rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900"
>
    <p class="mb-3 text-xs font-medium text-gray-500 dark:text-gray-400">
        معاينة نتيجة البحث
    </p>

    <div class="space-y-1">
        <div class="masar-ltr text-xs text-gray-600 dark:text-gray-400" dir="ltr">
            masar.sa<span x-text="'/' + (locale || 'ar') + '/' + (slug || 'article-slug')"></span>
        </div>

        <div
            class="text-lg leading-snug text-[#1a0dab] dark:text-[#8ab4f8]"
            style="font-family: 'Readex Pro', system-ui, sans-serif;"
            x-text="shownTitle"
        ></div>

        <div class="text-sm leading-relaxed text-gray-600 dark:text-gray-400" x-text="shownDescription"></div>
    </div>
</div>
