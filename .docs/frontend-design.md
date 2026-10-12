# Public site design

Visual system of the public (front) site: server-rendered Nette + Latte pages
styled by one stylesheet. Admin keeps its own NiceAdmin styles and is not part of
this design.

## Where things live

- `www/assets_front/front-custom.css`: design tokens (`:root` `--bripeon-*`) and all
  components. Class names use the `bripeon-` prefix.
- `www/assets_front/fonts/`: self-hosted variable fonts (Manrope for headings, Inter
  for text; latin + latin-ext subsets for Slovak diacritics) with their OFL licenses.
  No third-party font host is contacted.
- `app/UI/Modules/Front/templates/@layout.latte`: header, footer, mobile sticky CTA.
- `app/UI/Modules/Front/templates/@icons.latte`: inline SVG icon sprite
  (`<svg class="bripeon-icon"><use href="#i-name"/></svg>`).
- `www/assets_front/js/main.js`: mobile menu and account dropdown.
- `www/assets_front/js/demand-wizard.js`: progressive enhancement of the demand form
  into three steps; validates each step with the Nette form rules.

## Palette

Primary deep green `#0d6f50` (white text 6.2:1), tint `#e7f4ed`, ink `#14201b`,
muted `#5b6b64`, border `#dce6e0`, amber accent `#f59e0b` only for stars. Status
badges: `bripeon-badge-success|warning|info|neutral`.

## Behavior that must be preserved

- Header, hero and footer link to `/demand/create`; `.build/verify-beta-deploy.cjs`
  requires at least three such links on the home page.
- The provider sign-up React wizard and `tests/Cases/Frontend/ProviderSignUpTest.cjs`
  depend on the `bripeon-wizard-*`, `bripeon-primary-button`, `bripeon-secondary-button`,
  `bripeon-form-*`, `bripeon-progress` and `bripeon-hidden-submit` classes.
- Nette's default form renderer prints tables; `.bripeon-main form` styles turn them
  into stacked fields. Without JavaScript the demand form stays a single page.
- Server-side validation and the CSRF protection of every form are unchanged.

## Known gaps and follow-ups

- Category tiles on the home page are static and link to the demand form without
  preselecting a category. A data-driven list with prefill needs presenter support.
- The home page shows a labelled sample review. Replace it with real published
  reviews once there is a repository query for them.
- Terms, privacy policy, cookies and contact pages do not exist yet, so the footer
  does not link to them.
- Admin styles, a dark theme and the provider dashboard layout are not redesigned.
- Template leftovers that are no longer loaded (`www/assets_front/vendor/{aos,glightbox,
  purecounter,swiper,isotope-layout,imagesloaded,waypoints,php-email-form}`, unused
  images) are kept because the frontend test fixture still references some of them.
