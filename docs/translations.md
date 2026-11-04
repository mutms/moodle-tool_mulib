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

- `mdl-lang-normalise public/admin/tool/muprog` sorts the strings and writes them in the standard
  form, `--check` only reports files that are not normalised.
- Then have a native speaker who knows the feature review the result plugin by plugin.

## Downloaded language packs

Moodle loads `$CFG->dataroot/lang/<lang>/` (packs downloaded from AMOS) after `lang/<lang>/` of a
plugin, so downloaded strings override the shipped ones key by key. MuTMS translations are reviewed
together with the code, so by default `mulib::purge_downloaded_translations()` deletes the downloaded
files of all MuTMS plugins for languages in `mulib::TRANSLATIONS`. Other languages stay.

- It runs when a language pack is installed or updated (`tool_langimport` events), on install and
  upgrade of `tool_mulib` and hourly in the `tool_mulib\task\lang_cleanup` scheduled task.
- It is disabled by unchecking `tool_mulib/purgedownloadedlang`, in Site administration > Language >
  MuTMS translations.
- Language customisation (`<lang>_local` packs) is never touched, it is the supported way to change
  individual strings on a site.
- Other plugins can do the same with
  `tool_mulib\local\lang_util::purge_downloaded_translations($component, $languages)`.

## Testing with other languages

Tests must not download language packs. `tests/fixtures/lang/<lang>/` in `tool_mulib` has
a few core files of each language in `mulib::TRANSLATIONS` and of `en_us` (`langconfig.php`,
`moodle.php`, `admin.php`, `calendar.php`, `calendartype_gregorian.php` and `timezones.php`, where
the pack has them), copied from the core packs. `en_us` matters for date and time formats, the
built-in `en` is historically Australian English (`en_AU` locale, day before month). Add more core files there when a test needs them. They make a minimal pack in the test dataroot, plugin strings come from `lang/<lang>/` of
the plugins, everything else falls back to English. The pack is deleted with the test dataroot.

- PHPUnit: `\tool_mulib\tests\fake_langpack::install('cs');`
- Behat: `Given fake "cs" language pack is installed`

String customisations work for any installed language, downloaded or fake. They are written to
`$CFG->langlocalroot/<lang>_local/`, the same files the language customisation tool creates. Unlike the core `"language customisations"` Behat generator they
do not depend on the current user language and do not need the slow tool checkout.

- PHPUnit: `\tool_mulib\local\lang_util::customise_string('cs', 'tool_muprog', 'programs', 'Studijní plány');`,
  `null` value removes the customisation; or the `tool_mulib` data generator
  `create_lang_customisation(['lang' => 'cs', 'component' => ..., 'stringid' => ..., 'value' => ...])`.
- Behat: `the following "tool_mulib > lang_customisations" exist:` with columns `lang` (empty means
  `en`), `component` (`core`, `core_langconfig`, `mod_mudeck`, ...), `stringid` and `value`.

## Backporting

Translate on the newest branch, then copy the translations to older branches with mpd, run in the
older project:

```
cd /srv/projects/mutms45
mdl-lang-backport --dry-run /srv/projects/mutms53 admin/tool/muprog
mdl-lang-backport /srv/projects/mutms53 admin/tool/muprog
mdl-lang-backport --changed /srv/projects/mutms53 admin/tool/muprog
mdl-lang-check admin/tool/muprog
```

- The default run copies only strings with the same English text in both branches, those are safe.
- `--changed` copies strings whose English differs and lists each with both English texts. Review
  and fix every one of them by hand, the translation follows the newer English.
- Strings that exist only in the older branch are reported as untranslated, translate them there.
- Existing translations are kept, `--update` overwrites them when the English is the same.

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
| certification assignment | A user being put into a certification. It allocates the user to the programs of the certification. |
| certification period | The time a user holds a certification. One user has several periods over time. |
| certification window | The time limit for getting the certification, opens and closes within a period. |
| revoked | A certification taken away from the user by someone else. Differs from expired. |
| grace period | Extra time after a deadline. |
| evidence | A record proving completion or explaining a certification outside of normal tracking. |
| framework | A set of rules shared by related items, named after core competency frameworks. |
| delete | The thing stops existing. |
| remove | The thing stays, only its link to something else goes away. |
| cleanup task | A scheduled maintenance task. It often does more than delete old data. |

### Allocation and assignment

English uses "allocate" for programs and tenants and "assign" for certifications (and roles, as
core). The two words matter where both levels meet: assigning a user to a certification allocates
them to its programs, so certification strings such as "Not allocated" or "users are de-allocated
from programs" talk about program allocations. Translations must keep program allocation and
certification assignment apart. Tenant allocation may take either word, choose what reads naturally
for moving an account into an organisation.

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
| allocate to tenant | přiřadit ke klientovi | English says allocate, Czech uses the certification word. |
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

Standard German for partners in Germany, Austria and Switzerland, formal `Sie` and `ß` like the core
German pack. Partners adapt it locally where needed (`de_ch`, `de_du` or language customisation).
The packs are a preview produced by AI, a note in each file header says so until they are reviewed.

### Terms

| English | German | Notes |
|---|---|---|
| tenant | Mandant | Used by Moodle Workplace marketing and other plugins. |
| multi-tenancy | Mehrmandantenfähigkeit | |
| tenant manager | Mandantenmanager/in | |
| tenant user / global user | Nutzer/in des Mandanten / globale/r Nutzer/in | |
| associated user | verbundene/r Nutzer/in | Not `zugeordnet`, that is program allocation. |
| allocate to tenant | einem Mandanten zuweisen | English says allocate, `zuweisen` reads naturally for moving an account. |
| cohort | globale Gruppe | Core term. Never only `Gruppe`, that is a course group. |
| advanced cohort | erweiterte globale Gruppe | |
| candidate cohort | globale Gruppe der Kandidat/innen | |
| curator | verantwortliche Person | Not `Kurator`. |
| curated (cohort type) | manuell verwaltet | |
| access (list of users) | berechtigte Nutzer/innen | `Zugriff` only for granting access. |
| permission | Recht | Core `Rechte`. |
| capability | Fähigkeit | Core term. |
| site | Website | Core term. |
| category | Kursbereich | Core term. |
| program | Programm | Not `Lernpfad`. |
| program allocation | Zuordnung | `einem Programm zuordnen`. Also SAML user mapping (`Nutzerzuordnung`). |
| allocation source | Zuordnungsquelle | |
| self allocation | Selbstzuordnung | |
| course set | Kursset | |
| program item | Element | Core `Aspekt` is for gradebook items only. |
| certification | Zertifizierung | |
| certificate | Zertifikat | |
| certification assignment | Zuweisung | `einer Zertifizierung zuweisen`. Not `Zuordnung`. |
| certification period | Zertifizierungszeitraum | Short labels may use `Zeitraum`. |
| certification window | Zeitfenster | In full `Zeitfenster für die Zertifizierung`. Never `Zeitraum`. |
| grace period | Nachfrist | Core term. |
| revoked | widerrufen | Differs from `abgelaufen` (expired). |
| recertification | Rezertifizierung | |
| evidence | Nachweis | `Abschlussnachweis` for completion evidence. |
| sign up key | Anmeldeschlüssel | |
| framework | Rahmen | Core `Kompetenzrahmen`. Credits: `Punkterahmen`, relations: `Beziehungsrahmen`. |
| training credits | Weiterbildungspunkte | |
| supervisor / subordinate | Vorgesetzte/r / Mitarbeiter/in | |
| team | Team | |
| privileged session | privilegierte Sitzung | `starten` and `beenden`. |
| privilege | Privileg | `Recht` is reserved for permissions. |
| cache | Cache | Core term. |
| cron | Cron | |
| suspended | gesperrt | Core term. |
| next / previous | Weiter / Zurück | Core term. |
| data breach | Datenleck | |
| custom report | nutzerdefinierter Bericht | Core term. |
| completion | Abschluss | `Abschlussverfolgung` only for the core feature. |
| due date | Fälligkeitsdatum | `überfällig` for overdue. |
| notification | Benachrichtigung | Message providers follow core `Systemnachricht`. |
| tag | Tag | Core term. |
| custom field | nutzerdefiniertes Feld | Core term. |
| identity provider / service provider | Identitätsanbieter / Dienstanbieter | IdP and SP stay. |
| plugin | Plugin | |
| user | Nutzer/in, plural Nutzer/innen | Core gender forms. |

### Rules

- **Address users** with `Sie`, buttons and actions in the infinitive: `Programm hinzufügen`.
- **Delete and remove:** `löschen` when a thing stops existing or data is wiped, `entfernen` when a
  link goes away and the thing stays. Clearing a field or filter follows core (`Löschen`) unless
  that is ambiguous next to a delete action, then `Zurücksetzen`.
- **Edit and update:** actions that open a form are `… bearbeiten` (`Programm bearbeiten` for "Update
  program"), `aktualisieren` only for refreshing, recalculating and events.
- **Cleanup tasks:** `Bereinigung`.
- **Placeholders do not decline.** Keep `{$a}` where any value fits: `Programm „{$a}“ wurde gelöscht`.
- **Counts:** never `{$a} Nutzer/innen`, singular breaks. Use `Anzahl der Nutzer/innen: {$a}`.
- **Moodle** is not declined: `in Moodle`.
- **Quotes:** `„text“`.
- **Status words** are capitalised as labels (`Abgelaufen`, `Widerrufen`) and follow the noun in
  sentences.
- **Word sense:** status `Open` is `Offen`, not `Öffnen`. Start and end are easy to swap in column
  labels, check them.
