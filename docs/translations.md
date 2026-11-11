# Translations of MuTMS plugins

Rules and settled terminology for the language packs shipped in `lang/<lang>/` of MuTMS plugins.
Read this before adding, changing or translating any string.

## Workflow

- `lang/en` is the source. Translate from English, never through another translation.
- The MuTMS developer is fluent in English and Czech, so these two packs are the source of truth.
  Where the English is ambiguous, the Czech string shows the intended meaning. Other languages are
  AI previews until a native speaker reviews them.
- Every language shipped by a plugin has the same keys in the same alphabetical order as `lang/en`.
  A new English string needs its translation in the same change.
- Read how a string is used before translating it: find the `get_string()` call and look at what
  `{$a}` holds. Most bad translations come from guessing the meaning of a single isolated word.
- For standard Moodle terms follow the core language pack of the target language
  (`$CFG->dataroot/lang/<lang>/`, for example `moodle.php`, `admin.php`, `cohort.php`). Do not follow
  packs of other plugins blindly, their quality varies.
- `muform_datetimeformat` in tool_mulib is a PHP `date()` format, not the `strftime` style with `%`
  used in Moodle langconfig: Czech `j. n. Y H:i` shows `4. 11. 2026 14:30`, US English `n/j/Y g:i A`
  shows `11/6/2026 2:30 PM`. The same string decides the day and month order users type. Formats
  with `%` are ignored and ISO `Y-m-d H:i` is used instead.
- English variants such as `lang/en_us` are child packs of `en`, they contain only the strings that
  differ, for example `muform_datetimeformat` in tool_mulib. Such override-only packs are not listed in
  `mulib::TRANSLATIONS`.
- `mulib::TRANSLATIONS` lists the languages every plugin must ship. `tests/phpunit/lang_test.php` in
  `tool_mulib` fails when a plugin has missing or extra strings in any of them.
- In an mpd development VM check placeholders, HTML tags, URLs and string order too:

```
mdl-lang-check public/admin/tool/muprog
```

- `mdl-lang-normalise --header public/admin/tool/muprog` sorts the strings and writes them and the
  file header in the standard form, `--check` only reports files that are not normalised.
- Then have a native speaker who knows the feature review the result plugin by plugin.

## File header

Translation files start with the licence lines of the plugin's `lang/en` file, followed by this
block. Only the component, language code, language name, year and copyright holder change. The
`NOTE` paragraph marks an AI preview, remove it once a native speaker has reviewed the file.
`mdl-lang-normalise --header` rebuilds the header in this form, it keeps the `@copyright` lines and
the note.

```
/**
 * Strings for component 'tool_muprog', language 'de'.
 *
 * NOTE: This German translation was produced automatically by an AI language
 * model and has not yet been reviewed by a human translator. Corrections are
 * welcome.
 *
 * @package    tool_muprog
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
```

## Downloaded language packs

Moodle loads `$CFG->dataroot/lang/<lang>/` (packs downloaded from AMOS) after `lang/<lang>/` of a
plugin, so downloaded strings override the shipped ones key by key. MuTMS translations are reviewed
together with the code, so by default `mulib::purge_downloaded_translations()` deletes the downloaded
files of all MuTMS plugins for languages in `mulib::TRANSLATIONS`. Other languages stay.

- It runs when a language pack is installed or updated (`tool_langimport` events), on install and
  upgrade of `tool_mulib` and once a day in the `tool_mulib\task\lang_cleanup` scheduled task.
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
built-in `en` is historically Australian English (`en_AU` locale, day before month). Add more core
files there when a test needs them. They make a minimal pack in the test dataroot, plugin strings
come from `lang/<lang>/` of the plugins, everything else falls back to English.

- PHPUnit: `\tool_mulib\tests\fake_langpack::install('cs');`
- Behat: `Given fake "cs" language pack is installed`

String customisations work for any installed language, downloaded or fake. They are written to
`$CFG->langlocalroot/<lang>_local/`, the same files the language customisation tool creates. Unlike
the core `"language customisations"` Behat generator they do not depend on the current user language
and do not need the slow tool checkout.

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

## Terms

Language codes are the pack names: `cs`, `de`, and the European `fr`, `it`, `pt` and `es` (not
`fr_ca`, `pt_br` or `es_mx`). Notes on single terms are in the language sections below.

| English | cs | de | fr | it | pt | es |
|---|---|---|---|---|---|---|
| tenant | klient | Mandant | locataire | tenant | inquilino | inquilino |
| multi-tenancy | víceklientský provoz | Mehrmandantenfähigkeit | architecture multilocataire | multi-tenancy | multi-inquilino | multiinquilino |
| tenant manager | správce klienta | Mandantenmanager/in | gestionnaire du locataire | manager del tenant | gestor do inquilino | gestor del inquilino |
| tenant user | uživatel klienta | Nutzer/in des Mandanten | utilisateur du locataire | utente del tenant | utilizador do inquilino | usuario del inquilino |
| global user | globální uživatel | globale/r Nutzer/in | utilisateur global | utente globale | utilizador global | usuario global |
| associated user | přidružený uživatel | verbundene/r Nutzer/in | utilisateur associé | utente associato | utilizador associado | usuario asociado |
| allocate to tenant | přiřadit ke klientovi | einem Mandanten zuweisen | affecter à un locataire | assegnare a un tenant | atribuir a um inquilino | asignar a un inquilino |
| cohort | globální skupina | globale Gruppe | cohorte | gruppo globale | grupo global | cohorte |
| advanced cohort | rozšířená globální skupina | erweiterte globale Gruppe | cohorte avancée | gruppo globale avanzato | grupo global avançado | cohorte avanzada |
| candidate cohort | globální skupina kandidátů | globale Gruppe der Kandidat/innen | cohorte de candidats | gruppo globale dei candidati | grupo global de candidatos | cohorte de candidatos |
| curator | odpovědná osoba | verantwortliche Person | responsable | responsabile | responsável | responsable |
| curated (cohort type) | ručně spravovaná | manuell verwaltet | gérée manuellement | gestito manualmente | gerido manualmente | gestionada manualmente |
| access (list of users) | oprávnění uživatelé | berechtigte Nutzer/innen | utilisateurs autorisés | utenti autorizzati | utilizadores autorizados | usuarios autorizados |
| permission | oprávnění | Recht | permission | autorizzazione | permissão | permiso |
| capability | pravomoc | Fähigkeit | capacité | privilegio | permissão | capacidad |
| site | stránky | Website | site | sito | site | sitio |
| course | kurz | Kurs | cours | corso | disciplina | curso |
| category | kategorie | Kursbereich | catégorie | categoria | categoria | categoría |
| program | program | Programm | programme | programma | programa | programa |
| program allocation | přidělení | Zuordnung | affectation | allocazione | alocação | adscripción |
| allocate to program | přidělit do programu | einem Programm zuordnen | affecter à un programme | allocare a un programma | alocar a um programa | adscribir a un programa |
| allocation source | zdroj přidělení | Zuordnungsquelle | source d’affectation | fonte di allocazione | fonte de alocação | fuente de adscripción |
| self allocation | samoobslužné přidělení | Selbstzuordnung | auto-affectation | autoallocazione | autoalocação | autoadscripción |
| course set | sada | Kursset | ensemble de cours | set di corsi | conjunto de disciplinas | conjunto de cursos |
| program item | položka | Element | élément | elemento | elemento | elemento |
| certification | certifikace | Zertifizierung | certification | certificazione | certificação | certificación |
| certificate | certifikát | Zertifikat | certificat | certificato | certificado | certificado |
| certification assignment | přiřazení | Zuweisung | assignation | assegnazione | atribuição | asignación |
| assign to certification | přiřadit k certifikaci | einer Zertifizierung zuweisen | assigner à une certification | assegnare a una certificazione | atribuir a uma certificação | asignar a una certificación |
| certification period | certifikované období | Zertifizierungszeitraum | période de certification | periodo di certificazione | período de certificação | periodo de certificación |
| certification window | lhůta | Zeitfenster | fenêtre de certification | finestra di certificazione | janela de certificação | ventana de certificación |
| grace period | ochranná lhůta | Nachfrist | délai de grâce | periodo di tolleranza | período de tolerância | periodo de gracia |
| revoked | zrušený | widerrufen | révoqué | revocato | revogado | revocado |
| expired | vypršelý | abgelaufen | échu | scaduto | expirado | expirado |
| recertification | recertifikace | Rezertifizierung | recertification | ricertificazione | recertificação | recertificación |
| evidence | doklad | Nachweis | preuve | attestazione | comprovativo | evidencia |
| sign up key | klíč k přihlášení | Anmeldeschlüssel | clef d’accès | chiave di accesso | chave de acesso | clave de acceso |
| framework | rámec | Rahmen | référentiel | quadro | quadro | marco |
| training credits | kredity za vzdělávání | Weiterbildungspunkte | crédits de formation | crediti formativi | créditos de formação | créditos de formación |
| supervisor / subordinate | nadřízený / podřízený | Vorgesetzte/r / Mitarbeiter/in | supérieur / subordonné | supervisore / subordinato | supervisor / subordinado | supervisor / subordinado |
| team | tým | Team | équipe | team | equipa | equipo |
| privileged session | privilegovaná relace | privilegierte Sitzung | session privilégiée | sessione privilegiata | sessão privilegiada | sesión privilegiada |
| privilege | privilegium | Privileg | privilège | privilegio | privilégio | privilegio |
| cache | mezipaměť | Cache | cache | cache | cache | caché |
| suspended | pozastavený | gesperrt | suspendu | sospeso | suspenso | suspendido |
| next / previous | další / předchozí | Weiter / Zurück | Suivant / Précédent | Successivo / Precedente | Seguinte / Anterior | Siguiente / Anterior |
| data breach | únik dat | Datenleck | fuite de données | violazione dei dati | violação de dados | filtración de datos |
| custom report | vlastní sestava | nutzerdefinierter Bericht | rapport personnalisé | report personalizzato | relatório personalizado | informe personalizado |
| completion | dokončení | Abschluss | achèvement | completamento | conclusão | finalización |
| due date / overdue | termín splnění / po termínu | Fälligkeitsdatum / überfällig | date limite / en retard | data limite / in ritardo | data limite / em atraso | fecha límite / atrasado |
| notification | oznámení | Benachrichtigung | notification | notifica | notificação | notificación |
| tag | štítek | Tag | tag | tag | palavra-chave | etiqueta |
| custom field | vlastní pole | nutzerdefiniertes Feld | champ personnalisé | campo personalizzato | campo personalizado | campo personalizado |
| identity provider | poskytovatel identity | Identitätsanbieter | fournisseur d’identité | provider di identità | fornecedor de identidade | proveedor de identidad |
| service provider | poskytovatel služeb | Dienstanbieter | fournisseur de services | provider di servizi | fornecedor de serviços | proveedor de servicios |
| plugin | plugin | Plugin | plugin | plugin | módulo | plugin |
| user | uživatel | Nutzer/in | utilisateur | utente | utilizador | usuario |
| delete / remove | see Czech | löschen / entfernen | supprimer / retirer | eliminare / rimuovere | apagar / remover | eliminar / quitar |
| edit / update | upravit | bearbeiten / aktualisieren | modifier / mettre à jour | modificare / aggiornare | editar / atualizar | editar / actualizar |
| cleanup task | údržba | Bereinigung | nettoyage | pulizia | limpeza | limpieza |

- Core decides where it has the term, even when it surprises: Czech and German cohort is a "global
  group", Portuguese course is `disciplina` and plugin `módulo`, Italian capability is `privilegio`,
  French expired is `échu`.
- Tenant has no core term. German, French, Italian, Portuguese and Spanish use the word of Microsoft
  cloud products, Czech uses `klient`, not `tenant`.
- Cron, IdP and SP stay in every language.

## Rules for every language

- **Placeholders do not decline** and bring no article. Rephrase so that any value fits:
  `Program „{$a}“ byl odstraněn`, `Programm „{$a}“ wurde gelöscht`, `Programme « {$a} » supprimé`.
- **Counts:** never `{$a} users`, the noun form is wrong for some numbers. Use a label:
  `Počet uživatelů: {$a}`, `Anzahl der Nutzer/innen: {$a}`, `Nombre d’utilisateurs : {$a}`.
- **Status words** follow the gender of the noun they describe, so check where the string is shown.
- **Period and window:** short labels may use the bare word for period (`období`, `Zeitraum`), the
  window never takes that word.
- **Frameworks** follow the core competency framework: `kreditní rámec`, `Punkterahmen`,
  `référentiel de crédits`.
- **Edit and update:** actions that open a form use the edit word (`Upravit`, `Programm bearbeiten`
  for "Update program"), the update word is only for refreshing, recalculating and events.
- **Word sense:** check the feature, not the dictionary. Status `Open` is not the verb, start and
  end are easy to swap in column labels.
- **Keys are not the meaning.** String keys follow CRUD names (`*_create`, `*_update`, `*_delete`),
  the English text says what happens. Where only a link or membership changes, the text is Add and
  Remove: `deletecourse` is "Remove course", `item_create_course` is "Add courses". Translate the text.

## Czech

- **Terms:**
  - Core calls a site `stránky`, used here too, except where it could be read as pages.
  - Not `kohorta`, `kurátor` or `kurátorovaná`.
  - `přístup` only for granting: `Udělit přístup`.
  - Certification assignment is never `přidělení`, tenant allocation uses `přiřadit` too.
  - Certification period is not `období certifikace`. The window is in full `lhůta pro získání
    certifikace`.
  - Revoked is not `odvolaný` and not `neplatný`, which would also cover expired.
  - Privileged session: `zahájit` and `ukončit`, not `spustit`. `oprávnění` is reserved for permissions.
  - Cron is declined: `cronu`, `v cronu`.
  - Not `následující` on buttons.
  - Custom report is the core term, other reports are `přehled`.
  - `Sledování plnění` only for the core feature name.
- **Delete and remove:**
  - `odstranit` when a whole thing stops existing: a program, chapter, tenant or content block.
  - `smazat` when content or data is wiped: old history, cached data, all data of a program.
  - `odebrat` when a link goes away and the thing stays: a member from a cohort.
  - `vymazat` for clearing a field and for the course purge.
  - Core uses `odstranit` for remove too, keep it where core has the same string: `Odstranit ze
    zobrazení`, `Odstranit hvězdičku`.
- **Cleanup:** `čištění` means making something shiny in Czech. Use `údržba` for maintenance tasks and
  `odstraňování` when things are removed. `čištění` is fine for text and HTML.
- **Buttons and actions:** infinitive, `Přidat oznámení`.
- **Addressing users:** plural, `vyberte`.
- **Moodle:** declined after a preposition, `v Moodlu`, `do Moodlu`. Unchanged as a label after a
  noun, `Uživatel Moodle`.
- **Quotes:** `„text“`.
- **Dates:** `4. 11. 2026 14:30` (ČSN 01 6910), `muform_datetimeformat` is `j. n. Y H:i`.
- **Status words:** period statuses are neuter (`Zrušené`), certification statuses are feminine (`Platná`).
- **Word sense:** `escape HTML` is `escapovat HTML`, not `uniknout`. `show label` is `popisek pro
  zobrazení`, not `pořadu`. `disclosure` of a solution is `zobrazení řešení`, not `zveřejnění`.

## German

Standard German for partners in Germany, Austria and Switzerland, formal `Sie` and `ß` like the core
German pack. Partners adapt it locally where needed (`de_ch`, `de_du` or language customisation).

- **Terms:**
  - `Mandant` is also used by Moodle Workplace marketing.
  - Associated is not `zugeordnet`, that is program allocation. `Zuordnung` is also the SAML user
    mapping (`Nutzerzuordnung`). Certification assignment is never `Zuordnung`.
  - Never only `Gruppe` for a cohort, that is a course group. Not `Kurator`, not `Lernpfad`.
  - `Zugriff` only for granting access.
  - Core `Aspekt` is for gradebook items only.
  - The window is in full `Zeitfenster für die Zertifizierung`.
  - `Abschlussnachweis` for completion evidence.
  - Privileged session: `starten` and `beenden`. `Recht` is reserved for permissions.
  - `Abschlussverfolgung` only for the core feature.
  - Message providers follow core `Systemnachricht`.
  - Users are `Nutzer/in`, plural `Nutzer/innen`, the core gender forms.
- **Address users** with `Sie`, buttons and actions in the infinitive: `Programm hinzufügen`.
- **Delete and remove:** `löschen` when a thing stops existing or data is wiped, `entfernen` when a
  link goes away and the thing stays. Clearing a field or filter follows core (`Löschen`) unless
  that is ambiguous next to a delete action, then `Zurücksetzen`.
- **Moodle** is not declined: `in Moodle`.
- **Quotes:** `„text“`.
- **Dates:** `04.11.2026 14:30` (DIN 5008), `muform_datetimeformat` is `d.m.Y H:i`.
- **Status words** are capitalised as labels (`Abgelaufen`, `Widerrufen`).
- **Word sense:** status `Open` is `Offen`, not `Öffnen`.

## French, Italian, Portuguese and Spanish

Formal register for partners in Switzerland and the EU.

- **Terms:** French `inscription`, Italian `iscrizione` and Portuguese `inscrição` are core
  enrolment, Spanish core uses `matriculación`. Do not use them for allocation or assignment.
- **Address users** formally: French `vous`, Spanish `usted`, Portuguese third person without
  `você` (`Tem de indicar…`). Italian is impersonal where possible, otherwise `tu` like core.
- **Buttons and actions:** infinitive in French, Portuguese and Spanish (`Ajouter`, `Adicionar`,
  `Añadir`), imperative in Italian (`Aggiungi`, `Elimina`) like core.
- **Gender:** generic masculine like core, no inclusive forms (`·e`, `/a`, `@`).
- **Status words:** certification is feminine in all four (`Révoquée`, `Revocata`, `Revogada`,
  `Revocada`).
- **French typography:** apostrophe `’`, quotes `« texte »`, and a no-break space (U+00A0) inside
  the quotes and before `:`, `;`, `!` and `?`, all like core.
- **Italian, Portuguese and Spanish quotes:** straight `"text"` like core. Spanish questions and
  exclamations open with `¿` and `¡`.
- **Dates:** `04/11/2026 14:30`, `muform_datetimeformat` is `d/m/Y H:i` in all four.
