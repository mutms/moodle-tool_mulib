# Translations of MuTMS plugins

Rules and settled terminology for the language packs shipped in `lang/<lang>/` of MuTMS plugins.
Read this before adding, changing or translating any string.

## Workflow

- `lang/en` is the source. Translate from English, never through another translation.
- Every language shipped by a plugin has the same keys in the same alphabetical order as `lang/en`.
  A new English string needs its translation in the same change.
- Read how a string is used before translating it: find the `get_string()` call and look at what
  `{$a}` holds. Most bad translations come from guessing the meaning of a single isolated word.
- For standard Moodle terms follow the core language pack of the target language
  (`$CFG->dataroot/lang/<lang>/`, for example `moodle.php`, `admin.php`, `cohort.php`). Do not follow
  packs of other plugins blindly, their quality varies.
- `mulib::TRANSLATIONS` lists the languages every plugin must ship. `tests/phpunit/lang_test.php` in
  `tool_mulib` fails when a plugin has missing or extra strings in any of them.
- In an mpd development VM check placeholders, HTML tags, URLs and string order too:

```
mdl-lang-check public/admin/tool/muprog
```

- Then have a native speaker who knows the feature review the result plugin by plugin.

- Packs installed from AMOS into `$CFG->dataroot/lang/<lang>/` override `lang/<lang>/` of a plugin
  key by key, so a change here is not visible on a site where AMOS has the same key.

## What must stay unchanged

- Placeholders `{$a}`, `{$a->name}` and `{no}`: the same set in every string, the order may change.
- HTML tags and attributes, URLs, markdown syntax, code and setting identifiers in backticks.
- Technical and product names: SAML, CSV, JSON, Moodle, MuTMS, Boost, Have I Been Pwned.
- Strings that are only a format, for example `{$a}` or `{$a->name}: {$a->count}`.
- PHP date formats, for example `Y-m-d H:i` in `muform_datetimeformat`.

Examples in help texts may be translated when that makes them clearer, so the backticked code of
such strings is reported only as a warning by `mdl-lang-check`.

## Concepts

What the terms mean, for every language. The English word alone is often not enough.

| English | Meaning |
|---|---|
| tenant | An isolated organisation inside one site. Sites may rename it, for example to a faculty. |
| global user | A user that does not belong to any tenant. |
| associated user | A global user taking part in a tenant through the associated users cohort. |
| tenant allocation | Moving a user account into a tenant, the user loses access to other tenants. |
| cohort | Core Moodle cohort. Not necessarily global, it can belong to a category or tenant. |
| curator | The person responsible for a cohort, shown to users. Grants no permissions. |
| curated cohort | A cohort with membership managed by hand, as opposed to rules. |
| access (cohort) | The list of users with delegated permissions for one cohort. |
| program allocation | A user being put into a program. Certifications use "assignment" instead. |
| certification period | The time a user holds a certification. One user has several periods over time. |
| certification window | The time limit for getting the certification, opens and closes within a period. |
| revoked | A certification taken away from the user by someone else. Differs from expired. |
| grace period | Extra time after a deadline. |
| evidence | A record proving completion or explaining a certification outside of normal tracking. |
| framework | A set of rules shared by related items, named after core competency frameworks. |
| delete | The thing stops existing. |
| remove | The thing stays, only its link to something else goes away. |
| cleanup task | A scheduled maintenance task. It often does more than delete old data. |

## Czech

Core Moodle calls a site `stránky`, and it is used here too, except where it could be read as pages.

### Terms

| English | Czech | Notes |
|---|---|---|
| tenant | klient | Not `tenant`. |
| multi-tenancy | víceklientský provoz | |
| tenant manager | správce klienta | |
| tenant user / global user | uživatel klienta / globální uživatel | |
| associated user | přidružený uživatel | |
| allocate to tenant | přiřadit ke klientovi | Differs from programs. |
| cohort | globální skupina | Core term, even though it is not always global. Not `kohorta`. |
| advanced cohort | rozšířená globální skupina | |
| candidate cohort | globální skupina kandidátů | |
| curator | odpovědná osoba | Not `kurátor`. |
| curated (cohort type) | ručně spravovaná | Not `kurátorovaná`. |
| access (list of users) | oprávnění uživatelé | `přístup` only for granting: `Udělit přístup`. |
| permission | oprávnění | |
| capability | pravomoc | Core term. |
| program | program | |
| program allocation | přidělení | `přidělit do programu`. |
| allocation source | zdroj přidělení | |
| certification | certifikace | |
| certificate | certifikát | |
| certification assignment | přiřazení | Not `přidělení`. |
| certification period | certifikované období | Not `období certifikace`. Short labels may use `období`. |
| certification window | lhůta | In full `lhůta pro získání certifikace`. Never `období`. |
| grace period | ochranná lhůta | |
| revoked | zrušený | Not `odvolaný`, not `neplatný`, which would also cover expired. |
| recertification | recertifikace | |
| evidence | doklad | |
| sign up key | klíč k přihlášení | |
| framework | rámec | Core `Kompetenční rámec`. Credits: `kreditní rámec`, relations: `rámec vztahů`. |
| training credits | kredity za vzdělávání | |
| supervisor / subordinate | nadřízený / podřízený | |
| team | tým | |
| privileged session | privilegovaná relace | `zahájit` and `ukončit`, not `spustit`. |
| privilege | privilegium | `oprávnění` is reserved for permissions. |
| cache | mezipaměť | |
| cron | cron | Declined: `cronu`, `v cronu`. |
| suspended | pozastavený | Core term. |
| next / previous | další / předchozí | Core term. Not `následující` on buttons. |
| data breach | únik dat | |
| custom report | vlastní sestava | Core term. Other reports: `přehled`. |
| completion | dokončení | `Sledování plnění` only for the core feature name. |
| due date | termín splnění | |
| identity provider | poskytovatel identity | |
| service provider | poskytovatel služeb | |
| plugin | plugin | |

### Rules

- **Delete and remove:**
  - `odstranit` when a whole thing stops existing: a program, chapter, tenant or content block.
  - `smazat` when content or data is wiped: old history, cached data, all data of a program.
  - `odebrat` when a link goes away and the thing stays: a member from a cohort.
  - `vymazat` for clearing a field and for the course purge.
  - Core uses `odstranit` for remove too, keep it where core has the same string: `Odstranit ze
    zobrazení`, `Odstranit hvězdičku`.
- **Cleanup:** `čištění` means making something shiny in Czech. Use `údržba` for maintenance tasks and
  `odstraňování` when things are removed. `čištění` is fine for text and HTML.
- **Buttons and actions:** infinitive, `Přidat oznámení`. Update is `Upravit`.
- **Addressing users:** plural, `vyberte`.
- **Placeholders do not decline.** Rephrase so that the value stays nominative: `Program „{$a}“ byl
  odstraněn`, `Odstranit: {$a}`.
- **Counts:** never `{$a} uživatelů`, the form is wrong for most numbers. Use `Počet uživatelů: {$a}` or
  `Zpracovaní uživatelé: {$a}`.
- **Moodle:** declined after a preposition, `v Moodlu`, `do Moodlu`. Unchanged as a label after a
  noun, `Uživatel Moodle`.
- **Quotes:** `„text“`.
- **Gender of status words** follows the noun they describe, so check where the string is shown. Period
  statuses are neuter (`Zrušené`), certification statuses are feminine (`Platná`).
- **Word sense:** check the feature, not the dictionary. `escape HTML` is `escapovat HTML`, not `uniknout`.
  `show label` is `popisek pro zobrazení`, not `pořadu`. `disclosure` of a solution is `zobrazení řešení`,
  not `zveřejnění`.

## German

`mod_mudeck` is the only plugin with `lang/de` so far, so `de` is not in `mulib::TRANSLATIONS` yet. Terms
for the concepts above have not been decided, take them from the core German pack first.
