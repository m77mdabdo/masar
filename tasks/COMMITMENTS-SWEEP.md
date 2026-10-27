# Public commitments, against what the code enforces

Swept 2026-09-16. Every claim the site makes to a reader about how this
publication behaves, checked against the implementation.

**19 claims. 14 kept. 3 intended. 2 broken.**

**Resolved 2026-09-16: 17 kept, 3 reworded, 0 broken.** One of the two broken
claims was fixed in code, the other rewritten as intent with the gap recorded;
a fourth claim found during the rewording was enforced rather than weakened.

The distinction that matters:

- **Kept** — the code enforces it. A reader can rely on it.
- **Intended** — true of how we mean to work, not enforced by anything. Honest
  as an editorial promise, but it must not be worded as a system guarantee.
- **Broken** — the page says the system enforces it, the system does not, and
  the wording claims otherwise.

---

## Broken

### 1. "A writer cannot fact-check their own article" — editorial standards — **FIXED**

> «لا يستطيع الكاتب تدقيق مادته بنفسه — هذا قيد مفروض في النظام، لا عرف
> يُتبع أحيانًا.»
>
> *("A writer cannot fact-check their own article — this is a constraint
> imposed in the system, not a convention sometimes followed.")*

**The constraint exists and nothing calls it.** `ArticlePolicy::factCheck()`
implements the rule correctly and is referenced by **its own tests and nothing
else** — no controller, no action, no Filament page.

The transition that actually stamps the fact-check authorises `transition`, not
`factCheck`, and `transition` returns **true** when the user owns the article.

Proven against the real path, not read off the source:

    writer: كاتب (id 3)
    article author_id: 3
    policy says factCheck allowed? NO
    transition succeeded: editor_review
    fact_checker_id recorded: 3  (author_id 3)
    SELF-CHECKED: YES — the writer checked their own work

So a writer moves their own article through fact-check and the system records
them as its fact-checker. The page tells readers this is impossible.

This is the most serious kind on this list: a claim about editorial integrity,
stated as a system guarantee, contradicted by the system — and the policy test
passing is why it survived. The test tests the rule; nothing tests the path.

**This is a bug, not an overclaim.** The intent is unambiguous — the policy
exists with a comment explaining it. It wants code, not rewording.

**Fixed 2026-09-16.** `TransitionArticleStatus` now authorises `factCheck` when
an article leaves the fact-check stage, *and* enforces the invariant directly —
a policy is a question the UI asks, and a path that does not ask gets through,
which is the failure itself. Six tests drive the real transition path; none is a
policy unit test, because a passing policy test is what concealed this.

**Two more orphans found by the same sweep.** `ArticlePolicy::publish()` and
`ArticlePolicy::seo()` had no caller in `app/`, `resources/` or `routes/`
either. Both are now wired, and neither was harmless:

- **publish** — the panel disabled the button on gate failures and never on the
  permission. The `seo` role carries `article.update.any` and no
  `article.publish`, so an SEO specialist could open any article at `ready` with
  a clean gate and was shown a live green publish button whose only possible
  answer was an exception. Now `->disabled(! $user->can('publish', $article))`,
  with the tooltip separating the two reasons.
- **seo** — the SEO tab was ungated entirely. It carries the `noindex` toggle,
  so **any writer who could edit an article could remove it from search**, and
  the change leaves no visible trace on the page. Now hidden unless the policy
  allows it; hidden rather than disabled because Filament does not dehydrate a
  hidden field.

`tests/Feature/Filament/PolicyReachabilityTest.php` — 7 tests, driving the panel
rather than the policy. Four of the seven fail against the previous code,
including the one that writes `noindex` as a writer.

### 2. "We add a correction line to the article" — corrections page

> «نصحّح النص نفسه، ونضيف في أسفل المادة سطر تصحيح يذكر ما كان مكتوبًا، وما
> صار، ومتى جرى التعديل… إذا كان الخطأ جوهريًا يظهر التصحيح في أعلى المادة.»

**There is no correction anywhere in the system.** No column, no model, no
component, nothing rendered on an article. `articles.updated_content_at` exists
but is used only for `dateModified` in JSON-LD and `lastmod` in the sitemap —
never shown to a reader.

I wrote this page two days ago and described a feature that does not exist.
Either build it or reword it; both are defensible, but the current state is not.

**Rewritten 2026-09-16**, on the timeline ruling. Every sentence is now first
person and describes what we do; the page says in as many words that the
corrections log is being built and is not there yet. One thing was made true
rather than reworded: `updated_content_at` was already written on every edit and
surfaced only in the sitemap and the JSON-LD, so the byline now shows «آخر
تحديث» when an article was edited more than five minutes after publication — a
promise that was being kept to crawlers and not to readers. Corrections as a
mechanism is in `DEFERRED.md` as a real gap in the trust model, with what it
needs.

---

## Intended, not enforced — wording, not code

### 3. "Every quote is attributed to a real person who agreed to it"

Editorial practice. Nothing can enforce consent in code. `people.is_expert` is
never seeded true, which is the right guard, but the claim is broader than
anything the system checks.

### 4. "Paid content does not pass through the same editorial path"

There is no separate workflow for sponsored content — it moves through the same
statuses. The disclosure is enforced; the separation is a practice.

### 5. "Editorial decisions are not subject to advertisers"

A governance statement. Correctly unenforceable, and worth stating.

These three are fine as editorial promises. They read as system guarantees in
places, and the fact-check sentence shows what that costs when it is wrong.

**Reworded 2026-09-16.** All three now say what we do, and each page separates
the sentence the system enforces from the sentence we are trusted on:

- *Sources* — «لا تُنشر مادة دون مصدر» stays as a system rule because it is one.
  Attribution and consent moved into their own paragraph, opening «التزام
  تحريري نلتزم به نحن ولا يفرضه النظام، لأنه ليس مما يمكن للنظام أن يفرضه».
- *Paid content* — «لا يمر على مسار التحرير نفسه» is gone; there is no separate
  workflow. Replaced with what is true: the label is a gate rule, the separation
  is how we work.
- *Advertising* — «هذا ليس وعدًا عامًا» is gone. The page now says «هذا قرارنا
  في كيفية العمل، وهو ما تُحاسبوننا عليه», then names the one part the system
  does hold: publish rights are tied to an editorial role.

### A fourth claim found while rewording — now enforced

The advertise page also said «لا يمكن إزالة الوسم بعد النشر». Nothing stopped
it: `is_sponsored` was a plain toggle on a published article. It was cheap to
make true rather than weaken, so it is enforced now — an `Article::saving` guard
throwing `DisclosureCannotBeWithdrawn`, the toggle disabled in the panel with
the reason in its helper text, and five tests. The way back is deliberate and
visible: unpublish, then unmark. It moves from this list to **Kept**.

---

## Kept — the code enforces these

| claim | enforced by |
|---|---|
| An article that leaves «لماذا يهم» empty cannot be published | `require_why_it_matters`, publish gate |
| No article publishes without at least one source with a link | `require_source` |
| No article publishes without a hero image and its alt text | `require_hero_image`, `require_hero_alt` |
| No article publishes without passing fact-check | `require_fact_check` — the *stage* is enforced; who performed it is claim 1 |
| A 30-second summary is required, 2–5 points | `require_summary`, min/max |
| Sponsored content cannot publish without a named sponsor | `require_sponsor_name` |
| Paid placement is disclosed at the top of the article | `article/hero`, `article/meta` |
| Paid placement is disclosed in the page data search engines read | `schema/news-article` `sponsor` |
| Paid placement is disclosed everywhere it appears | `x-article.sponsored-flag`, all six card weights, file-level test |
| Every status change is logged with who made it | `RecordStatusAudit`, `causedBy($actor)` |
| The four questions structure the article | `article_blocks.section`, renderer |
| We publish no figure without a source and a date | `market.as_of` gate, source line |
| The demo site is not indexed | demo-mode switch: robots meta, robots.txt, sitemaps, banner |
| Advertisement links do not pass ranking signals | `rel="nofollow sponsored noopener"` |
| A writer cannot fact-check their own article | `TransitionArticleStatus`, `ArticlePolicy::factCheck` |
| The sponsored label cannot be removed after publication | `Article::saving` → `DisclosureCannotBeWithdrawn` |
| Nobody outside editorial can publish | `article.publish` permission, held by three roles |

---

## One chain worth naming

**Every contact email is empty.** `contact.editorial_email`,
`advertising_email`, `careers_email` and `general_email` are all unset.

The contact page degrades honestly — «لم يُضف بريد لهذا الفريق بعد» — but
three pages route readers there as the way to reach us: corrections ("write to
us via the contact page"), advertise ("write to us and we will come back with
details"), and about.

So the corrections policy tells a reader how to report an error, and the route
it gives them ends in an empty page. Not a false claim — the contact page does
not pretend — but the promise chain terminates in nothing. Settings, not code.

This is the same shape as the crawler's `contact@masar.sa`: an accountability
route that does not resolve.
