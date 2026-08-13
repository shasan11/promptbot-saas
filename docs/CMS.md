# Website CMS and SEO

The public website is database-driven and editable from Superadmin → Website & CMS. The overview reports publication, blog, media, form, lead, and redirect counts and provides common publishing shortcuts. Pages contain ordered blocks; the public renderer displays only published pages and non-hidden blocks.

## Editing workflow

The block registry in `config/cms.php` is the source of truth for the editor and renderer. It includes hero, logo cloud, feature variants, image/text, stats, testimonials, live or manual pricing, comparison tables, integrations, how-it-works, FAQ, CTA, newsletter, contact forms, video, gallery, rich text, spacers, and restricted custom HTML. Editors can add, drag, move, duplicate, hide, and remove blocks without editing raw section JSON.

Pages support draft, published, and scheduled states. Saving captures a revision. A revision can be restored, which also captures the pre-restore state. Preview URLs are signed, expire, and can render drafts; an obvious preview banner prevents confusion with production.

Blog articles support draft/published/scheduled workflow, sanitized HTML, author attribution, SEO fields, and assignable categories and tags. Categories and tags are managed in the Blog tab and render on public article detail.

The form builder creates a validated field schema using text, email, phone, and textarea controls with required flags. Forms can be activated or disabled. Public submissions accept only declared fields and store attribution, a keyed IP hash, lifecycle status, and private operator notes. Leads move through New, Contacted, Qualified, Won, Lost, or Spam and export to formula-injection-safe CSV. Seed data includes General Contact, Contact Sales, Request Demo, and Newsletter forms.

The starter seeder creates a polished, fully editable home page with hero, feature, image/text, how-it-works, pricing, testimonial, FAQ, CTA, and contact blocks. Pricing defaults to active public plans, supports an annual/monthly toggle, and carries plan and interval into customer registration; editors can deliberately choose manual cards instead.

Navigation supports separate Header, Mobile, and Footer groups, internal pages, safe external links, dropdown parents, buttons, new-tab behavior, active state, and drag ordering. Footer links are grouped and draggable. Theme settings cover logos, favicon, palette, heading/body fonts, button/card radii, container width, footer copy, social links, and copyright.

## Security

`CmsBlockRegistry` sanitizes block content on write and again at rendering boundaries. URLs accept safe schemes/paths. Rich text is allowlisted. Custom HTML requires `website.custom_html`; scripts, event handlers, and dangerous URL protocols are removed. Media uploads validate real image MIME types and dimensions; SVG is not accepted by the raster-image upload path.

## SEO and routing

Pages support SEO title/description, canonical URL, robots index/follow, Open Graph, Twitter, editable JSON-LD, and a SERP preview. Global settings provide title format, default description/OG image, Twitter card type, canonical base URL, crawler policy, site verification, and strictly validated Google Analytics, Tag Manager, and Meta Pixel identifiers. The public layout renders these values without accepting arbitrary scripts.

- `/sitemap.xml` contains only published, indexable pages.
- `/robots.txt` exposes crawler policy and the sitemap URL.
- Slug changes create redirects; the redirect manager rejects loops and records hit counts.
- CMS routes and system routes are registered before the public catch-all.

After changing public routes or deployment configuration, run `php artisan optimize:clear` and `php artisan route:cache`. Run the scheduler every minute so `cms:publish-scheduled` publishes eligible pages.

## Verification checklist

1. Edit the seeded home page and preview it while still a draft.
2. Publish it and verify metadata in page source.
3. Change its slug and verify the old path redirects exactly once.
4. Confirm no draft/noindex page appears in `/sitemap.xml`.
5. Upload a valid raster image, edit alt text/caption, replace it without changing its URL, and confirm invalid/SVG content is rejected. Confirm referenced media cannot be deleted.
6. Restore a revision and confirm its ordered block content returns.
7. Create a blog category and tag, assign them to an article, schedule/publish it, and verify the public article.
8. Modify a lead form field schema, submit it publicly, move the captured lead through its lifecycle, save private notes, and export CSV.
9. Build a header dropdown plus a dedicated mobile menu, drag them into order, and verify inactive items remain private.
10. Switch pricing between live plans and manual cards and verify both billing intervals link to registration.
