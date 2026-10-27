# Handover

Started 2026-09-16. Day 10 finishes it. Items land here as they are discovered,
so that the last day is assembly rather than recall.

---

## Settings that must be filled before launch

### Contact addresses — three public pages dead-end until these are set

`contact.editorial_email`, `contact.advertising_email`, `contact.careers_email`
and `contact.general_email` are all unset. The contact page degrades honestly —
«لم يُضف بريد لهذا الفريق بعد» — but three pages route a reader there as the way
to reach us and the route terminates in nothing:

| page | what it sends the reader to do |
|---|---|
| `corrections` | report a factual error in a published article |
| `advertise` | ask for rates and availability |
| `about` | contact the publication at all |

The corrections case is the one that matters: the page tells a reader how to
report an error we published, and the route it gives them ends in an empty page.
Owner sets these with the domain in Day 8-9.

This is the same shape as the crawler's `contact@masar.sa` — an accountability
route that does not resolve — which is why `masar.intelligence.polling_enabled`
is off until the domain exists.

### Also unset, lower stakes

- `integrations.newsletter_key` — deliberately kept with helper text saying it is
  unused pending an ESP choice.
- `integrations.analytics_id` — decided, not yet wired.

---

## Things that are load-bearing and do not look it

### JSON-LD escaping

**`App\Support\JsonLd::encode()` is a security control, not a formatting
helper.** Every `<script type="application/ld+json">` block on the site goes
through it, and a test refuses to let anything else encode one.

The flags are the reason it exists. `JSON_HEX_TAG` turns `<` and `>` into
`\u003C` / `\u003E`. Without it, an article whose headline contains
`</script>` **ends the script element early** and everything after it is parsed
as HTML — stored XSS, on every page that article appears on, executing for every
reader, and reachable by anyone who can type a headline. That was live until
2026-09-16: five schema components each encoded with
`JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`, and a title of
`</script><img src=x onerror=alert(1)>` put two working breakouts on the
rendered article page.

Two things follow for whoever maintains this:

- **Adding a schema component?** Call `JsonLd::encode()`. If you call
  `json_encode()` instead, `tests/Feature/Security/InvariantsTest.php` fails
  with the filename — that is the test doing its job, not a false positive, and
  the fix is to use the helper rather than to relax the test.
- **Changing the flags?** `JSON_UNESCAPED_UNICODE` keeps Arabic readable and is
  safe to keep. `JSON_HEX_TAG` and `JSON_HEX_AMP` are the protection. Removing
  either reintroduces the vulnerability, and
  `tests/Feature/Security/RenderedOutputTest.php` will say so.

### nginx `add_header` does not inherit

If a `location` block sets **any** `add_header`, it discards every `add_header`
from the server block. That is why `deploy/nginx/security-headers.conf` exists
as a snippet and is `include`d in the server block *and* inside each location
that sets a `Cache-Control` of its own. Adding a new location that sets a header
without that include silently drops `nosniff` and the rest for those paths —
which was the live state for the build assets, the fonts and all media until
2026-09-16.

### Two package defaults we were wrong about

Filament **allows** what it cannot resolve a policy for; "no policy" is not
"denied". And Filament's login rate limit is real but is the package's, not
ours. Both are now held by tests. See CLAUDE.md §5 and §7.

## Queued for this document

- `tasks/STALE-SNAPSHOT-INVENTORY.md` — the full list of cached or denormalised
  values and what rebuilds each, including the ones that are not currently
  broken.
- Deployment: host, user, secrets path, the four scripts in `deploy/`, and the
  restore drill.
- `php artisan masar:owner <email>` must be re-run and 2FA re-scanned; the owner
  account was lost when the dev database was wiped on 2026-09-14.
- Three video clips to re-source (over the repository weight budget).

## Roles, as they now stand

`media.manage` was removed on 2026-09-16: four roles held it and nothing in the
codebase checked it. Nothing changed in practice — it granted no capability —
but it means **social, video and designer are read-only panel users** and the
role editor no longer shows a «الوسائط» group. Whoever sets up the newsroom
should know that before handing those roles out. See `DEFERRED.md` for the media
library that brings the permission back.

The subscriber list is now editor-in-chief and super-admin only. It was open to
every staff member who could reach the panel, CSV export included.
