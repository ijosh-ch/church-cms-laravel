<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Data model

The app is the IFGF Taipei–Zhongli member and attendance system (`PRD.md` 3.0): Laravel 13,
PHP 8.4, PostgreSQL 17. This section draws it twice, as the database (ERD) and as the code's
classes. The table-by-table inventory, the queries against the indexes, the personal data per
table and the ranked review findings are in [`DATA_MODEL.md`](DATA_MODEL.md).

### Database — entity-relationship diagram

There are 12 domain tables. `sessions` belongs to the framework and is drawn because
`sessions.user_id` points at a member. `cache`, `cache_locks`, `jobs`, `job_batches` and
`failed_jobs` are left out, because no domain table refers to them.

How to read it:
- Types are PostgreSQL types.
- A quoted `null` means the column is nullable.
- A quoted enum name is the PHP enum that backs a string column.
- `created_at` and `updated_at` exist on every table except `sessions` and are omitted.

```mermaid
erDiagram
  branches ||--o{ members : "home branch"
  branches ||--o{ teams : "team of"
  branches ||--o{ events : "held at"
  households ||--o{ members : groups
  institutions ||--o{ members : "studies or works at"
  teams ||--o{ members : "icare_team_id"
  teams ||--o{ member_team : has
  members ||--o{ member_team : "serves in"
  teams ||--o{ events : hosts
  events ||--o{ occurrences : "happens on"
  occurrences ||--o{ attendances : has
  members ||--o{ attendances : attends
  members ||--o{ attendances : "recorded_by"
  occurrences ||--o| headcounts : "counted as"
  members ||--o{ change_requests : about
  members ||--o{ change_requests : "requested_by"
  members ||--o{ import_batches : "created_by"
  members ||--o{ sessions : "user_id"

  branches {
    bigint id PK
    varchar code UK
    varchar name
    varchar name_zh "null"
    varchar gmaps_url "null"
    boolean is_active
  }
  teams {
    bigint id PK
    varchar type "TeamType"
    varchar name
    bigint branch_id FK "null"
    smallint meeting_weekday "null"
    varchar ability "TeamAbility, null"
    boolean is_active
  }
  households {
    bigint id PK
    varchar name
  }
  institutions {
    bigint id PK
    varchar country UK "with code"
    varchar code UK "null"
    varchar name_en
    varchar name_zh "null"
  }
  members {
    bigint id PK
    uuid public_ref UK
    bigint household_id FK "null"
    varchar household_role "HouseholdRole, null"
    bigint branch_id FK
    bigint icare_team_id FK "null"
    bigint institution_id FK "null"
    varchar full_name
    varchar chinese_name "null"
    varchar email UK "null"
    varchar phone "null, indexed"
    varchar line_id "null"
    date birthday "null"
    varchar domicile_taiwan "null"
    varchar occupation "null"
    varchar education_level "EducationLevel, null"
    varchar school_or_company "null"
    varchar category "MemberCategory"
    varchar status "MemberStatus"
    varchar qr_token_hash UK "null"
    text qr_token_enc "encrypted, null"
    varchar short_code UK "null"
    varchar calendar_event_id "null"
    timestamp calendar_synced_at "null"
    timestamp profile_reminded_at "null, pending"
    timestamp email_verified_at "null"
    varchar remember_token "null"
    varchar legacy_ref "null, indexed"
    timestamp deleted_at "null"
  }
  member_team {
    bigint id PK
    bigint member_id FK
    bigint team_id FK
    varchar role "TeamRole"
    timestamp left_at "null"
  }
  events {
    bigint id PK
    varchar type "EventType"
    varchar name
    bigint branch_id FK "null"
    bigint team_id FK "null"
    varchar recurrence "Recurrence"
    smallint weekday "null"
    boolean is_active
  }
  occurrences {
    bigint id PK
    bigint event_id FK "UK with occurs_on"
    date occurs_on
    timestamp locked_at "null"
  }
  attendances {
    bigint id PK
    bigint occurrence_id FK "UK with member_id"
    bigint member_id FK "null"
    varchar guest_name "null"
    varchar mode "AttendanceMode"
    varchar method "AttendanceMethod"
    varchar category "MemberCategory, null"
    bigint recorded_by FK "null"
    timestamp recorded_at
  }
  headcounts {
    bigint id PK
    bigint occurrence_id FK, UK
    integer adult
    integer college
    integer teens_youth
    integer kids
    integer online
    integer total
    varchar reporter "null"
    varchar source
  }
  change_requests {
    bigint id PK
    bigint member_id FK
    bigint requested_by FK
    jsonb changes
    varchar token_hash UK
    varchar status
    timestamp expires_at
    timestamp decided_at "null"
  }
  import_batches {
    bigint id PK
    varchar source
    varchar file_hash "indexed"
    varchar mode
    varchar status
    jsonb summary "null"
    bigint created_by FK "null"
  }
  sessions {
    varchar id PK
    bigint user_id "null, indexed, no FK"
    varchar ip_address "null"
    text user_agent "null"
    text payload "encrypted"
    integer last_activity
  }
```

### Class diagram

#### How the class diagram lines up with the ERD

They share a structure only where the code holds data. Each Eloquent model maps to one table,
and each foreign key becomes a pair of relation methods: `belongsTo` on the class that holds the
key, and `hasMany` or `hasOne` on the other. The cardinalities are the same in both drawings.
Everything else differs, in one of two directions.

**In the database but not a class:**
- `member_team` is a pivot table. In the code it is the `belongsToMany` between `Member` and
  `Team`, and its `role` and `left_at` columns are pivot attributes.
- `sessions`, `cache` and `jobs` have no model.
- Indexes, unique and partial-unique constraints, and ON DELETE rules exist only in the database.

**In the code but not a table:**
- The 12 enums are classes. Each one is stored as a `varchar` on the table that uses it.
  `PhoneCountry` is not stored anywhere: it only picks the expected phone shape on the form.
- Some behaviour has no column: `Member::hasAbility()`, `leads()`, `needsProfile()`, and the
  `category` recomputed on every save.
- Hidden and encrypted attributes exist only in the code.
- `Member extends Authenticatable`; tables have no inheritance.
- Controllers, form requests, rules, services, jobs, mail, observers and commands have no table
  at all. They are the application around the data, drawn in diagrams 2 to 4.

#### 1 · Domain models and enums (mirrors the ERD)

```mermaid
classDiagram
  direction LR
  class Branch {
    +string code
    +string name
    +string name_zh
    +string gmaps_url
    +bool is_active
    +members() HasMany~Member~
    +teams() HasMany~Team~
    +events() HasMany~Event~
  }
  class Team {
    +TeamType type
    +string name
    +int branch_id
    +int meeting_weekday
    +TeamAbility ability
    +bool is_active
    +branch() BelongsTo~Branch~
    +members() BelongsToMany~Member~
    +leaders() BelongsToMany~Member~
    +icareMembers() HasMany~Member~
    +events() HasMany~Event~
  }
  class Household {
    +string name
    +members() HasMany~Member~
  }
  class Institution {
    +string country
    +string code
    +string name_en
    +string name_zh
    +members() HasMany~Member~
  }
  class Member {
    <<Authenticatable>>
    +string public_ref
    +HouseholdRole household_role
    +string full_name
    +string chinese_name
    +string email
    +string phone
    +string line_id
    +Date birthday
    +string domicile_taiwan
    +string occupation
    +EducationLevel education_level
    +string school_or_company
    +MemberCategory category
    +MemberStatus status
    -string qr_token_hash
    -string qr_token_enc
    +string short_code
    +string calendar_event_id
    +DateTime calendar_synced_at
    +DateTime profile_reminded_at
    +DateTime email_verified_at
    -string remember_token
    +string legacy_ref
    +DateTime deleted_at
    +branch() BelongsTo~Branch~
    +household() BelongsTo~Household~
    +institution() BelongsTo~Institution~
    +icareTeam() BelongsTo~Team~
    +teams() BelongsToMany~Team~
    +attendances() HasMany~Attendance~
    +changeRequests() HasMany~ChangeRequest~
    +hasAbility(TeamAbility ability) bool
    +leads(Team team) bool
    +needsProfile() bool
  }
  class Event {
    +EventType type
    +string name
    +int branch_id
    +int team_id
    +Recurrence recurrence
    +int weekday
    +bool is_active
    +branch() BelongsTo~Branch~
    +team() BelongsTo~Team~
    +occurrences() HasMany~Occurrence~
  }
  class Occurrence {
    +int event_id
    +Date occurs_on
    +DateTime locked_at
    +event() BelongsTo~Event~
    +attendances() HasMany~Attendance~
    +headcount() HasOne~Headcount~
  }
  class Attendance {
    +int occurrence_id
    +int member_id
    +string guest_name
    +AttendanceMode mode
    +AttendanceMethod method
    +MemberCategory category
    +int recorded_by
    +DateTime recorded_at
    +occurrence() BelongsTo~Occurrence~
    +member() BelongsTo~Member~
    +recorder() BelongsTo~Member~
  }
  class Headcount {
    +int occurrence_id
    +int adult
    +int college
    +int teens_youth
    +int kids
    +int online
    +int total
    +string reporter
    +string source
    +occurrence() BelongsTo~Occurrence~
  }
  class ChangeRequest {
    +int member_id
    +int requested_by
    +array changes
    -string token_hash
    +string status
    +DateTime expires_at
    +DateTime decided_at
    +member() BelongsTo~Member~
    +requester() BelongsTo~Member~
  }
  class ImportBatch {
    +string source
    +string file_hash
    +string mode
    +string status
    +array summary
    +int created_by
    +creator() BelongsTo~Member~
  }

  class TeamType {
    <<enumeration>>
    Icare
    Ministry
  }
  class TeamAbility {
    <<enumeration>>
    Scan
    Admin
  }
  class TeamRole {
    <<enumeration>>
    Leader
    Member
  }
  class HouseholdRole {
    <<enumeration>>
    Head
    Spouse
    Child
    Other
  }
  class EducationLevel {
    <<enumeration>>
    Sd
    Smp
    Sma
    Diploma
    S1
    S2
    S3
  }
  class MemberCategory {
    <<enumeration>>
    Kids
    TeensYouth
    College
    Adult
  }
  class MemberStatus {
    <<enumeration>>
    Active
    Inactive
    Visitor
  }
  class EventType {
    <<enumeration>>
    SundayService
    Icare
    WorshipNight
    ClassSession
    Special
  }
  class Recurrence {
    <<enumeration>>
    Weekly
    Annual
    Once
  }
  class AttendanceMode {
    <<enumeration>>
    Onsite
    Online
  }
  class AttendanceMethod {
    <<enumeration>>
    Qr
    Code
    Manual
    Import
  }
  class PhoneCountry {
    <<enumeration>>
    Taiwan
    Indonesia
    Malaysia
    Singapore
    HongKong
    Other
  }

  Branch "1" --> "*" Member : members
  Branch "1" --> "*" Team : teams
  Branch "1" --> "*" Event : events
  Household "1" --> "*" Member : members
  Institution "1" --> "*" Member : members
  Team "1" --> "*" Member : icareMembers
  Team "*" -- "*" Member : member_team
  Team "1" --> "*" Event : events
  Event "1" --> "*" Occurrence : occurrences
  Occurrence "1" --> "*" Attendance : attendances
  Occurrence "1" --> "0..1" Headcount : headcount
  Member "1" --> "*" Attendance : attendances
  Attendance "*" --> "0..1" Member : recorder
  Member "1" --> "*" ChangeRequest : changeRequests
  ChangeRequest "*" --> "1" Member : requester
  ImportBatch "*" --> "0..1" Member : creator

  Team ..> TeamType
  Team ..> TeamAbility
  Member ..> HouseholdRole
  Member ..> EducationLevel
  Member ..> MemberCategory
  Member ..> MemberStatus
  Event ..> EventType
  Event ..> Recurrence
  Attendance ..> AttendanceMode
  Attendance ..> AttendanceMethod
  Attendance ..> MemberCategory
```

#### 2 · Web requests: controllers and services

The controllers are thin. Each method receives its form request and the service it needs
through Laravel's container, so the signatures below are the dependency list. Every controller
extends `Controller`. Models appear by name only; their detail is in diagram 1. A dotted arrow
means "uses", and a solid arrow is a constructor dependency.

```mermaid
classDiagram
  direction LR
  namespace Controllers {
    class RegistrationController {
      +create(Request request) View
      +store(RegisterMemberRequest request, MemberRegistrar registrar) RedirectResponse
      +done() View
    }
    class GoogleController {
      +redirect(GoogleBirthday birthdays) RedirectResponse
      +callback(Request request, GoogleBirthday birthdays) RedirectResponse
    }
    class SignOutController {
      +__invoke(Request request) RedirectResponse
    }
    class HomeController {
      +__invoke(Request request, QrIssuer qr) View
    }
    class ProfileController {
      +edit(Request request) View
      +update(UpdateProfileRequest request, MemberEditor editor) RedirectResponse
    }
    class BranchController {
      +show() View
      +store(ChooseBranchRequest request) RedirectResponse
      +chosen(Request request)$ Branch
    }
    class ScanController {
      +show(Request request, AttendanceRecorder recorder) View
      +scan(RecordScanRequest request, AttendanceRecorder recorder, QrIssuer qr) JsonResponse
      +code(RecordCodeRequest request, AttendanceRecorder recorder) RedirectResponse
    }
    class RosterController {
      +show(Request request, AttendanceRecorder recorder) View
      +search(SearchRosterRequest request) RedirectResponse
      +tick(TickRosterRequest request, AttendanceRecorder recorder) RedirectResponse
    }
    class AttendanceTableController {
      +__invoke(ShowAttendanceTableRequest request) View
    }
    class MemberController {
      +index(Request request) View
      +search(SearchMembersRequest request) RedirectResponse
      +edit(Member member) View
      +update(UpdateMemberRequest request, Member member, MemberEditor editor) RedirectResponse
    }
    class MemberTeamController {
      +store(StoreMemberTeamRequest request, Member member) RedirectResponse
      +destroy(DestroyMemberTeamRequest request, Member member) RedirectResponse
    }
  }
  namespace Services {
    class MemberRegistrar {
      -PhoneNormalizer normalizer
      -QrIssuer qr
      +register(array data, bool emailVerified) Member
    }
    class MemberEditor {
      -PhoneNormalizer normalizer
      +attributes(array data) array
    }
    class AttendanceRecorder {
      +occurrenceFor(Branch branch, DateTimeInterface date) Occurrence
      +record(Occurrence occurrence, Member member, AttendanceMode mode, AttendanceMethod method, Member recorder) array
      +byToken(string token, QrIssuer qr) Member
      +byShortCode(string code) Member
    }
    class QrIssuer {
      +issue(Member member) string
      +newToken() string
      +hash(string token) string
      +newShortCode() string
      +svg(string token, int size) string
    }
    class GoogleBirthday {
      +enabled() bool
      +fetch(string token) string
    }
  }
  namespace Support {
    class PhoneNormalizer {
      +normalize(string raw) string
      +normalizeEmail(string raw) string
    }
    class Audit {
      +record(string action, Member subject, array context)$ void
    }
  }
  class WelcomeWithQr {
    +Member member
  }
  class Member
  class Occurrence
  class Attendance

  RegistrationController ..> MemberRegistrar
  GoogleController ..> GoogleBirthday
  HomeController ..> QrIssuer
  ProfileController ..> MemberEditor
  ScanController ..> AttendanceRecorder
  ScanController ..> QrIssuer
  RosterController ..> AttendanceRecorder
  RosterController ..> BranchController : chosen branch
  ScanController ..> BranchController : chosen branch
  MemberController ..> MemberEditor
  MemberController ..> Audit
  MemberTeamController ..> Audit

  MemberRegistrar --> PhoneNormalizer
  MemberRegistrar --> QrIssuer
  MemberRegistrar ..> WelcomeWithQr : queues
  MemberRegistrar ..> Member : creates
  MemberEditor --> PhoneNormalizer
  QrIssuer ..> Member : writes QR columns
  AttendanceRecorder ..> QrIssuer
  AttendanceRecorder ..> Occurrence : first or create
  AttendanceRecorder ..> Attendance : creates
```

#### 3 · Form requests and validation rules

All 12 form requests extend `FormRequest`. Only the three below use the app's own rules; the
other nine check their input with Laravel's built-in rules.

```mermaid
classDiagram
  direction LR
  class FormRequest {
    <<Laravel>>
  }
  class RegisterMemberRequest
  class UpdateProfileRequest
  class UpdateMemberRequest
  class ValidationRule {
    <<interface>>
    +validate(string attribute, mixed value, Closure fail) void
  }
  class PersonName
  class PhoneNumber {
    -bool strict
  }
  class NotRegistered {
    -string column
    -Member except
    -mixed birthday
    +isChild(mixed birthday)$ bool
  }
  class PhoneNormalizer {
    +normalize(string raw) string
    +normalizeEmail(string raw) string
  }
  class MemberRegistrar

  FormRequest <|-- RegisterMemberRequest
  FormRequest <|-- UpdateProfileRequest
  FormRequest <|-- UpdateMemberRequest
  ValidationRule <|.. PersonName
  ValidationRule <|.. PhoneNumber
  ValidationRule <|.. NotRegistered
  RegisterMemberRequest ..> PersonName
  RegisterMemberRequest ..> PhoneNumber : strict
  RegisterMemberRequest ..> NotRegistered : phone
  UpdateProfileRequest ..> PersonName
  UpdateProfileRequest ..> PhoneNumber
  UpdateProfileRequest ..> NotRegistered : phone and line_id
  UpdateMemberRequest ..> PersonName
  UpdateMemberRequest ..> PhoneNumber
  PhoneNumber ..> PhoneNormalizer
  NotRegistered ..> PhoneNormalizer
  MemberRegistrar ..> NotRegistered : isChild
```

#### 4 · Background work, the birthday calendar, commands and mail

Whenever a member is created, or their name, Chinese name, birthday or iCare changes,
`MemberObserver` queues `SyncBirthdayEvent` after the transaction commits.
`AppServiceProvider` binds `CalendarGateway` to the fake, or to Google when configured (never in
tests). It also defines the three Gates — `scan`, `lead-team` and `admin` — on top of
`Member::hasAbility()` and `leads()`.

The commands are `ifgf:admin:grant`, `ifgf:calendar:sync` and `ifgf:profile:remind` (daily at
19:00, Asia/Taipei).

```mermaid
classDiagram
  direction LR
  class Member {
    +hasAbility(TeamAbility ability) bool
    +leads(Team team) bool
  }
  class MemberObserver {
    +created(Member member)
    +updated(Member member)
  }
  class SyncBirthdayEvent {
    <<ShouldQueue>>
    +Member member
    +handle(BirthdaySync sync)
  }
  class BirthdaySync {
    -CalendarGateway calendar
    +sync(Member member)
    +remove(Member member)
  }
  class CalendarGateway {
    <<interface>>
    +create(BirthdayEvent event) string
    +update(string eventId, BirthdayEvent event)
    +delete(string eventId)
    +eventIds() array
  }
  class FakeCalendarGateway
  class GoogleCalendarGateway {
    -string calendarId
    -Closure accessToken
  }
  class GoogleAccessToken {
    -string keyPath
    +__invoke() string
  }
  class BirthdayEvent {
    <<readonly>>
    +string title
    +string description
    +string date
    +string until
    +forMember(Member member)$ BirthdayEvent
  }
  class CalendarException
  class AppServiceProvider {
    +register()
    +boot()
  }
  class GrantAdmin {
    +handle() int
  }
  class SyncCalendar {
    +handle(BirthdaySync sync, CalendarGateway calendar) int
  }
  class RemindToCompleteProfile {
    +handle() int
  }
  class WelcomeWithQr {
    +Member member
    +attachments() array
  }
  class CompleteYourProfile
  class QrIssuer
  class Audit

  Member ..> MemberObserver : observed by
  MemberObserver ..> SyncBirthdayEvent : dispatches
  SyncBirthdayEvent ..> BirthdaySync
  BirthdaySync --> CalendarGateway
  BirthdaySync ..> BirthdayEvent
  CalendarGateway <|.. FakeCalendarGateway
  CalendarGateway <|.. GoogleCalendarGateway
  GoogleCalendarGateway --> GoogleAccessToken
  CalendarGateway ..> CalendarException : throws
  AppServiceProvider ..> CalendarGateway : binds
  AppServiceProvider ..> Member : Gates
  SyncCalendar ..> BirthdaySync
  SyncCalendar ..> CalendarGateway
  GrantAdmin ..> Member
  GrantAdmin ..> Audit
  RemindToCompleteProfile ..> CompleteYourProfile : queues
  WelcomeWithQr ..> QrIssuer : QR attachment
```

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
