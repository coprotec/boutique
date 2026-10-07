# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this app is

A PHP training-course booking system ("boutique") for COPROTEC — a fresh, deliberately sober MVP rewrite. Users browse upcoming training sessions, register, and pay by card/wire/cheque/financing.

**Key shift vs. `boutique-old`: Navision (NAV/wsformation) is dropped.** It's replaced by a new third-party service, **SmartOF**, which becomes the single gateway for training sessions, registrations, and customer data. This app is no longer the system that owns the reservation business logic end-to-end — it's now a **storefront + payment tunnel**: it displays the session catalog (read from SmartOF), collects reservation info, takes payment (Monetico or offline methods), then pushes the reservation + payment to SmartOF through a dedicated gateway. See `CAHIER_DES_CHARGES.md` §5–6 for details.

**Project status: bootstrap/specification phase.** No application code exists yet. The v1 feature scope is confirmed in `CAHIER_DES_CHARGES.md` §5.2: guest-only reservation (no login yet), catalog read from SmartOF, reservation form ported from `boutique-old` but restructured with proper per-field validation, no promo codes, same financing types as before, no back-office. SmartOF API doc studied (§6); remaining open points are team questions (QE-n) and SmartOF-vendor questions (QSO-n) in `QUESTIONS_EQUIPES.md`, blocking ones listed in §15. Do not assume features beyond what's confirmed there.

## Relationship to sibling projects

This repo lives at `Project-Symfony/boutique/boutique/`, alongside other projects that are **not** part of this repo and must not be modified from here:

- `../boutique-old` (Git repo being renamed `boutique-old`) — legacy plain-PHP version (no framework, no ORM, handwritten PDO). Reference for **reservation flow and payment logic** (3-step booking, Monetico CB integration, wire/cheque/financing). Its ERP integration (NAV/wsformation) is **not** carried over — SmartOF replaces it — but its Monetico integration (`view/reservation/etape3.inc.php`, `config/config.local.php.example`) is the reference to port/refine. Its `CLAUDE.md` documents the domain in detail.
- `../boutique-sf/boutique` — a more advanced Symfony 7 + Vue 3 + Doctrine rewrite, judged too complex/over-engineered for current needs. Use it as a source of ideas or reusable snippets when relevant, but this new project is **not** a continuation of it — don't inherit its scope or abstractions by default.
- `../../coplanif` — reference for the **Webpack Encore + Vue 3 integration pattern** to follow here: multiple Vue entries (`assets/vue/*.vue`) mounted as targeted components overlaying Twig templates, not a full SPA (see its `webpack.config.js` and `assets/vue/`).

When in doubt about the reservation/payment flow (pricing, VAT, reservation steps, Monetico), check `../boutique-old` first. For anything ERP-related (session catalog, registration submission, customer data), the answer is SmartOF, not `boutique-old`/Navision — check the SmartOF API doc once available; don't port wsformation calls.

## Target stack

- PHP 8.2+, Symfony 7.x
- Doctrine ORM, MySQL
- Vue.js 3 for interactive UI parts, built with **Webpack Encore**. Organisation copied from `/coplanif`: single `vue` entry (`assets/vue/vue.js`) holding the component registry, components in domain folders (`assets/vue/reservation/`, `assets/vue/tool/FormType/`). **Deliberate difference:** no global `<div id="app">` compiled in the browser (public site: user-entered text echoed by Twig would be evaluated by Vue = template injection). Each component mounts on its own element: `<div data-vue="reservation-form" data-props="{{ props|json_encode|e('html_attr') }}">`, runtime-only Vue build.
- Docker: app container on `php:8.x-apache` (Apache inside the container, like `/coplanif`) + MySQL 8. On the VPS, the **host's existing Apache** (the one serving `boutique-old`) is the reverse proxy (HTTPS, basic auth/IP allowlist for préprod) in front of the container port
- Environments: dev (local Docker), préprod (must support end-to-end Monetico test-mode payment + reservation runs), prod
- Payment: **Monetico** retained (ported/refined from `boutique-old`), plus offline methods (wire, cheque, third-party financing) kept from day one

None of this is scaffolded yet. When starting the Symfony skeleton, keep dependencies minimal — only add bundles the confirmed MVP scope actually needs.

## Working conventions for this project

- **Stay sober.** This project exists specifically to avoid the complexity of `boutique-sf`. Don't add abstractions, bundles, or infrastructure "for later" — build only what the current confirmed scope requires.
- **Don't guess scope.** If a feature isn't in `CAHIER_DES_CHARGES.md` §5.2 (MVP périmètre) or hasn't been explicitly requested, ask rather than porting it over from `boutique-sf` or `boutique-old` by default.
- **Naming:** PHP class names, namespaces, business methods and console commands (`app:catalog:sync`…) are in **English**. Entity properties, getters/setters, DB tables/columns, comments, user-facing text and public URLs (`/formation`, `/reservation`) stay in French. `SmartofClient` methods mirror SmartOF API resource names (`sessionsOuvertes()`…). UI work follows the `design-boutique` skill (`.claude/skills/`).
- Update `CAHIER_DES_CHARGES.md` as decisions get made (§13 Décisions actées / §15 Points ouverts, §14 plan by lots) so it stays the current source of truth for scope.

## External integration

- **SmartOF** (replaces the old Navision/wsformation ERP) — single gateway for training sessions, registrations, and customer data. The catalog shown in the storefront is read from SmartOF; reservations are pushed to it through a dedicated gateway. API v2 doc studied (https://developers.smartof.fr) — integration design, limits and open choices are in `CAHIER_DES_CHARGES.md` §6; decided: direct enrolment (entreprises + apprenants + commanditaires, `customId` = order number), no `demandes_inscription`; only COPROTEC products are synced/sellable (filter criterion pending QE-4/QSO-3).
- ~~`https://wsformation.coprotec.net/MobileApp/` (Navision)~~ — **not used by this project.** Still documented in `../boutique-old/CLAUDE.md` for historical/business-logic reference only (e.g. what a "reservation" or "session" conceptually needs), never call it from here.
- **Monetico** (card payment) — retained, to be ported/refined from `../boutique-old` (HMAC-SHA1-signed POST to `p.monetico-services.com`, test endpoint `p.monetico-services.com/test/paiement.cgi`). A dedicated Monetico test environment (préprod) must stay usable for end-to-end payment + reservation test runs at any time.
- Wire transfer, cheque, third-party financing — offline payment methods kept from `boutique-old`, in scope for the MVP.
- Confirmation emails / PDF vouchers — ownership split between SmartOF (registration confirmation) and the boutique (possible payment receipt) is **not yet decided** (see `CAHIER_DES_CHARGES.md` §10).

## Data migration

Old reservations live in `boutique-old`'s MySQL database; the structure is captured in `../boutique-old/doc/db/boutique.sql` (`code_promo`, `evenements`, `mails_envoyes`, `paiements`, `participants`, `reservations`, `villes`). **That legacy structure is a data source only, not a schema to replicate** — extract the important records and convert them into this app's own Doctrine schema. Same principle applies to data that will later come from the SmartOF API: its shape doesn't dictate the target schema either, it gets mapped/converted on the way in. Track field-by-field mapping decisions in `CAHIER_DES_CHARGES.md` §7 as the target schema and the SmartOF doc firm up.

## Deployment

Hosted on `boutique-old`'s current VPS (Ubuntu 22.04, 2 GB RAM, SSH on port 2268), where both shops coexist until the switch-over: host **Apache** reverse proxy → app container (Apache inside) on 127.0.0.1:8095 (préprod) / 8096 (prod). Step-by-step server procedure: `DEPLOIEMENT_VPS.md`. Préprod `preprod.app.coprotec.net` (restricted access except the Monetico notification URL), prod on `boutique-old`'s current domain. Deploy via `deploy.sh <preprod|prod>` modelled on `/coplanif` (GitHub Actions over SSH). See `CAHIER_DES_CHARGES.md` §10–11.

## Commands

- Dev stack: `docker compose up -d --build` → http://localhost:8083 (Mailpit: http://localhost:8025). Front: `npm run watch`.
- DB: `php bin/console doctrine:migrations:migrate` (MySQL). Tests run on SQLite (`.env.test`): `php bin/phpunit`.
- Catalogue: `php bin/console app:catalog:sync`. Cron (see `docker/cron/crontab`): `app:orders:expire` every minute, `app:smartof:send [numero]` every 5 min (also for manual retry after an alert).
- Without credentials: `SMARTOF_FAKE=1` serves SmartOF from `fixtures/smartof/$SMARTOF_FAKE_FIXTURES` (`demo_api.json` demo catalogue for dev/préprod, `fake_api.json` pinned for tests; writes kept in `var/smartof-fake/<env>.json`); `MONETICO_SIMULE=1` (set in `.env.dev`) replaces the Monetico page with a local simulator that posts a signed notification.
- Branches: `preprod` (every push auto-deploys the préprod via GitHub Actions) and `main` (= prod: a push deploys prod after approval in GitHub's `prod` environment). Work on `preprod`, then release with a fast-forward merge `preprod → main`.
- Préprod / prod: `bash deploy.sh <preprod|prod>` on the VPS (refuses to run unless the checkout is on `preprod` / `main` respectively); secrets in `.env.<env>.local`, mounted as `.env.local`; host Apache vhost in `docker/apache-hote/`.
- Every business assumption awaiting an answer is marked `PROVISOIRE (QE-n / QSO-n)` in code and `.env` — grep for it when answers come back in `QUESTIONS_EQUIPES.md`.
