# Common Questions, and quicker message reminders

- **Common Questions:** a client's thread has a button on the left of the quotation pill, which opens three questions under the composer, each sent by its own button. The list is `COMMON_QUESTIONS` in `resources/js/lib/common-questions.ts`. The button is only its icon where its label would collide with the quotation pill. Staff never see it.
- **Staff message reminders** now go out after 3 minutes instead of 30 (`MESSAGE_REMINDER_AFTER_MINUTES`), and `messages:send-reminders` runs every minute instead of every five, so the delay holds. This covers the support pool, the staff member handling a thread and staff in group chats.
- **Clients** are emailed "waiting for your reply" (`AwaitingReplyNotification`) when they have not replied after 30 minutes (`MESSAGE_REMINDER_CLIENT_AFTER_MINUTES`), whether or not they have read the message. It replaces their unread reminder, and goes out once per batch of messages.

# Inquire asks what you need first

The steps are now **1. What do you need?**, **2. Which stones?**, **3. Other details** and **4. Review and send**. An inquiry asks for one service, chosen from two dropdowns, rather than a service per stone.

- **Stone type replaces the old stone grouping's name everywhere**: model `StoneType`, table `stone_types`, column `stones.stone_type_id`, the `*_stone_type` pivots, the `type` field on stone payloads, and the brand page's `type` query param. Project categories and furniture types keep their names. `specifications.stoneType` is gone: read `type.name`.
- **`service_types` and `services`**: ten services under Natural Stone & Applications and Custom Stone Furniture, each with a description and features. `inquiries.service_id` holds the choice, and the per-stone work table is gone. `furniture_types` is down to Tables, Seating, Storage and Decorative Furniture, with no link to services.
- **The request** requires `projects[i][serviceId]`, refused when the service or its type is no longer offered, unless an edit already has it. Help me choose still needs a service. A row repeating a stone, edge and finish is added to the first, up to the quantity limit, rather than refused; the page still points the repeat out before it is sent.
- **Editing**: nobody may edit an approved inquiry, the super-admin included. Staff can change the service, and still never the client's files.
- **The stone grid** uses `StoneCard` (picture, name, type; no save or view control) and `StoneTypeSelect` from `md` up or `StoneTypePills` below it. The type combines with the tab and the search.
- **The stones come from the server a page at a time** as the reader scrolls, 16 on a phone and 24 from a tablet up, like Our Collection. The tab, stone type and search are server filters (`stone_tab`, `stone_type`, `stone_search`), so the page no longer holds the whole catalogue. Whether a picked stone is still on offer is the request's to say; a restored draft asks for its own stones by slug (`pickedStones`), and an edit is sent its own (`heldStones`).
- **The result of sending is a dialog**, `ResultDialog`, over the page rather than in place of it, so the stepper no longer counts it as a step. Booking an appointment uses the same one.
- **Stored drafts** moved to version 8, so a draft from the old order is discarded.

These edits are in the create migrations, so an existing database needs `migrate:fresh --seed`.

# Confirmation popups

`ConfirmDialog` is the app's one way to ask before doing something. Cancelling an inquiry (it was the browser's `confirm()`), logging out and removing a message all use it.

# A simpler inquiry: two steps, one project

The stepper is now **1. Your request** and **2. Review and send**. The first step is four questions on one page, one open at a time; an answered question closes to a line with a Change button.

- **One project per inquiry.** "Add another project" is gone, and the server refuses a second (`config/inquiry.php`, `MAX_PROJECTS`). Visits skip the stone and "where it is used" questions.
- **Edge and finish** start as "Not sure yet, advise me" and fold away behind "Choose edge and finish (optional)". Landmark, area, files and notes fold away behind "Add more details (optional)".
- **The project name is automatic** ("Kitchen Islands - Natural Stone Supply") and can be changed on Review. A name the reader gave is kept on later edits.
- **Help me choose** sends an inquiry with no stone. New column `inquiries.stone_advice_requested`, added to the create migration, so an existing database needs `migrate:fresh --seed`. Staff and clients see "Stone to be recommended by Regray" on the inquiry page and the chat card.

# Request a quotation from the Regray Team thread

A client with no inquiry going sees a floating "Request a quotation" button in their Regray Team thread, which opens the stepper. It reads "Continue your request" when this browser holds their draft.

- **`canRequestQuote`** on the thread response (`ThreadController::messages`) is decided by the server: the viewer's own support thread, `inquiries.create`, and no active inquiry. Only `sales_status = rejected` or `process_status = completed` ends an inquiry; `marketing_status = lost` and payment do not.
- **The ×** hides it for this browser tab, under `quote-prompt-dismissed` in `sessionStorage`. Nothing is stored on the server.

# Best seller is a stone category

A category is a best seller, not a single stone. The top three active categories by completed inquiries count, ties included (`BestSellerService::BEST_SELLER_COUNT`), and each stone takes it from its primary category.

- **Product cards** no longer show the Best Seller tag, and their payloads no longer carry `bestSeller`.
- **The brand page's Natural Stones filter** stars the best-selling categories (`filterOptions.categories.*.bestSeller`).
- **The stone page** shows its Best Seller badge when the stone's category is one. The Inquire page's Best Sellers filter follows the same rule.

# Products belong to brands, and each brand page has the collection

Every stone and furniture piece now has one brand. The collection moved from Home and the landing page onto each brand's page, under "Our Collection". Product URLs and breadcrumbs are brand-scoped. Projects are unchanged.

## What changed

### Database

- **`brands`** moved into its own migration (`2026_09_29_000003`), so it runs before `stones` (`000004`) and `furniture` (`000015`).
- **`stones.brand_id`, `furniture.brand_id`**: required, indexed, FK to `brands`, `RESTRICT`. Slugs stay globally unique. These edits are in the create migrations, so an existing database needs `migrate:fresh --seed`.

### Seeders

- **`DatabaseSeeder`** calls `BrandSeeder` before `StoneSeeder`. `StoneSeeder` and `FurnitureSeeder` attach everything to `regray-stone`, and call `BrandSeeder` themselves so they still work alone. The other brands have no products.

### Routes

- **Guest**: `/brands/{brand}`, `/brands/{brand}/stones/{stone}`, `/brands/{brand}/furniture/{furniture}`. **Signed in**: the same under `/dashboard`, with the existing permissions. A signed-in user on a guest URL gets a 301 to the dashboard form, marked `no-store`.
- A product resolves only under its own active brand and while active (`Brand::resolveRouteBinding`, `resolveChildRouteBinding`).
- **Old URLs 301**: `/{slug}` for a stone, `/furniture/{slug}`, `/dashboard/home/stones/{stone}`, `/dashboard/home/furniture/{furniture}`, `/dashboard/home/brand/{brand}`, and `/dashboard/home/{slug}` for a stone. `/{slug}` and `/dashboard/home/{slug}` still show projects.

### Brand page

- **`BrandController`** lists the brand's products through one union query (`BrandRepository::paginateProducts`), so All Categories has one search and one pagination, ordered by name case-insensitively. A single kind keeps its `sort_order`.
- **Query**: `collection=all|stones|furniture`, with `category` (and `size`, `thickness`) for stones or `furniture_type` for furniture. A param from another collection is ignored.
- **Page size**: 16 at a time on phones, 24 from tablet up, from the `grid_page_size` cookie the browser sets, so `per_page` is gone from the URL.
- **`brand-collection.tsx`** holds the grid and filters, moved out of `home-catalogue.tsx`. The second select is hidden under All Categories. A brand with no active products shows "Products coming soon" and no filters.
- **Home and landing** keep the hero and the brand tiles. The `collection` param is gone from both controllers.

### Breadcrumbs

- `catalogueCrumbs` builds Regray / Home / Brand / Product from the payloads, which now carry `brand { slug, name }`. The workspace header shows it; guests see no breadcrumbs. The Saved doors keep their Saved trail.

# Brands and furniture on Home, brand and furniture pages, and no stone project types

Home now has one heading, "Our Collection", over the brands and the products. The brands come from the database, one full-width tile per row with a tagline, each linking to its own page. The second collection is Furniture, not projects. The stone profile drops Project Highlights and gets an "Ask a Quote" button. Stones no longer have project types. Run `php artisan migrate:fresh --seed`.

## What changed

### Database

- **`brands`** (in the projects migration, above `projects`): slug (unique), name, logo_path, thumbnail_path, tagline (120), description, website_url, is_active, sort_order. **`projects.brand_id`**: nullable FK to `brands`, `SET NULL` on delete.
- **`furniture`** (in the project categories migration): slug (unique), name, description, thumbnail_path, `project_category_id` (FK, `RESTRICT`), is_active, sort_order.
- **`saved_furniture`** (same migration): user_id and furniture_id (both FK, `CASCADE`), created_at; primary key on the pair, like `saved_stones`.
- **`project_category_stone` is gone**: its migration is deleted. Stones no longer filter by project type. `category_project` stays: projects still have types.

### Seeders

- **`BrandSeeder`** (before `ProjectSeeder`): the six brands in their old order. Each logo is copied from `database/seeders/data/brands/` to the public disk (`config('filesystems.public_disk')`) as `brands/<random>.webp`; seeding again keeps a logo that is already there. Descriptions are written from each brand's own site (taglines are ours, to replace with approved copy); websites are set where the brand's own site was confirmed.
- **`ProjectSeeder`**: a project's `brand_slug` sets its brand; Recidencia Del Hamor and Belo Medical Group are Regray Stone's. Stone project types are no longer attached.
- **`FurnitureSeeder`** (after `ProjectSeeder`): six sample pieces, Rounded Table among them, with no photos.
- The logos moved out of `public/images/brands/`, so they are served only from the disk.

### Backend

- **Home** (`Home\StoneController`): `?collection=furniture` lists furniture (`search`, `project_category`, `page`, `per_page`), anything else lists stones. The projects branch and the classes only it used are gone: `ListProjectsAction`, `ProjectFilterData`, `ProjectIndexRequest`, `ProjectService::listPublishedProjects`, `ProjectRepository::paginatePublished` and `::filterOptions`. `filterOptions.projectCategories` is now `filterOptions.projectTypes`, for the furniture select. `brands` (active, by sort order) and `filterOptions` are sent once (`Inertia::once`).
- **New pages**: `GET brands/{brand}` (`brands.show`, `BrandController`, `BrandProfileResource`) and `GET furniture/{furniture}` (`furniture.show`, `FurnitureController`, `FurnitureProfileResource`), both guarded by `home.view`. An unknown, inactive or malformed slug is a 404.
- **Saved furniture**: `PUT`/`DELETE saved/furniture/{furniture}` (`saved.furniture.store`/`.destroy`, `SavedFurnitureController`), guarded and throttled like saved stones. `savedFurnitureSlugs` is shared next to `savedStoneSlugs`, and the Saved page also gets `savedFurniture`.
- **Stone profile**: the payload no longer has `projects`.

### Frontend

- **Home**: the hero's "Discover Brands" button is gone. The brand tiles are as tall as the hero (now taller), link to their pages, and size each logo so the brand names read at one height (through a slug-keyed map); each brand's description sits inside its tile, right beside the logo in the bottom left (under it on phones). Stone and furniture cards are one `ProductCard` (was `StoneCard`), and the second select reads "All Product Types" under Furniture. The selects are Collection (Natural Stone, Furniture) and then the categories or project types for it. Changing the collection resets the second select and reloads.
- **Brand page** (`brand-profile`): hero, logo, name, tagline, description, a website button, and a "Coming soon" Collection section (the payload still carries the brand's projects). **Furniture page** (`furniture-profile`): a sample layout. Both are kept plain, for a redesign.
- **Stone profile**: Project Highlights is gone. "Ask a Quote" opens the inquiry stepper, for whoever may send one, and the save heart moves up above it.
- **Saving furniture**: furniture cards on Home and the furniture page have the same save heart as stones, and the furniture page has the same "Ask a Quote" button (`AskQuoteButton`, shared with the stone page). The Saved page lists furniture in its own section, and the sidebar's Saved count includes it. `useSavedStones` now sits on a generic `useSavedItems`, with `useSavedFurniture` beside it.
- Product grids use `gap-3.5 md:gap-4.5`.

# Inquiry and Leads tables, Update Status by role, and Manila time

The Inquiry page is now one table for every staff role, with the actions each role has on a row: View, Edit, the status of the track it owns, and Upload job order.

Logistics' first status is renamed **`order_received`** (was `received`), everywhere: enum, CHECKs, display value, seeds and labels ("Order received"). Each status move goes through one **Update Status** dialog, with a required note and the files and amount its target needs, and the server checks all of it again. The Leads page uses the same table. Files are grouped per status update, and the client sees them that way. The app now runs on Manila time. Run `npm install` (a new Radix package) and `php artisan migrate:fresh --seed`.

## What changed

### Database

- **`inquiry_files`:** `document_type` adds `billing_statement` and `invoice`; new `status_history_id` (FK `inquiry_status_history`, `RESTRICT`, indexed): the status update a file came with, so each update's files are one group.
- **`lead_status_history`:** new `note` (500), why a lead was won or lost.
- Both edits are in the tables' create migrations.

### The flow (ERD section 4)

- **Sales** moves to any value but its current one once started; `rejected` is final. **Quoted** is the client's verbal approval in the group chat and needs the quotation (client-visible) and the quoted total; the job order is optional there. Each quote is a new `quote_revision`. Quoting no longer starts payment.
- **Job order:** with the quote, or later from its own row action (`POST inquiries/{reference}/job-order`, sales, once). It starts logistics at `order_received`; until then the inquiry shows `quoted`.
- **Payment** starts NULL and only once quoted, forward only: `awaiting` with the billing statement, then `partial` or `fully_paid` with receipts, an invoice and the amount received (a `payment_transactions` row). A partial payment must leave a balance; `fully_paid` must bring the payments to exactly the quoted total.
- **Logistics** moves to any value but its current one once the job order has started it; `completed` is final. No files.

### Backend

- **Permissions:** `leads.update` (marketing), `inquiries.edit` (marketing, sales), `inquiries.status.sales` (sales), `inquiries.status.payment` (accounting), `inquiries.status.process` (logistics); the admin holds all. Technical and owner only read.
- **Endpoints:** `POST inquiries/{reference}/status` (track, to, note, `files[type][]`, `quotedTotal` or `amountReceived`; `InquiryPolicy::updateStatus` then `InquiryStatusUpdateService`, in one transaction); `POST leads/{reference}/status` (to, note; was a PATCH without a note); `GET inquiry/{reference}/edit` and `PATCH inquiry/{reference}` (staff Edit: the project only, never the client, a status or the client's files).
- **Rules:** `InquiryStatusRules` (allowed targets, required files, extra field), mirrored by `resources/js/lib/status-rules.ts`. `InquiryStatusService::transition()` takes a note and returns its history row.
- **Uploads:** `InquiryFileUploads` follows the track's permission instead of the role, so the admin may upload too: quotation and job order with `inquiries.status.sales`; billing statement, receipt and invoice with `inquiries.status.payment`.
- **Lists:** `ViewController::inquiry` and `::leads` send `{ data, meta }` and `filters` (`DataTableRequest`: search, page, perPage of 10, 25, 50 or 100). Each row's `actions` are the `Gate::allows` checks its routes make, plus whether the inquiry's state still allows them. Contact is the phone number; the Leads list also sends the email.
- **Details:** staff open any inquiry on `inquiry.show` (the list's View); a client still only their own. Documents come grouped per update, headed by its status and date; the job order is never sent, to anyone.
- **Timezone:** `app.timezone` is `Asia/Manila`, and the database session reads in the same zone. Eloquent converts every date to the app's zone before writing it, so a stored instant never moves; only display changes.

### Frontend

- New `ui/table`, `ui/pagination`, `ui/radio-group` (with `@radix-ui/react-radio-group`), `DataTable` (debounced search, page size, pager, skeleton rows, EmptyState; server-side through partial reloads), `RowActions` (one icon button per task, each with a tooltip and label), `StatusUpdateDialog`, `formatDate` ("June 22, 2020", in Manila).
- **Inquiry page:** Date, Inquiry No., Client, Contact (phone), Status (the inquiry status, and the payment status beside it once started), Actions; Add (marketing, sales, admin) opens the Platform or Direct modal.
- **Status colours:** every inquiry and payment status has its own colour (`statusTone()` in `lib/inquiry-statuses.ts`), the same on every page and for every role. The payment badge reads "Awaiting", not "Awaiting payment".
- **Sidebar:** Leads sits above Inquiry.
- **Direct (walk-in) client:** the phone number is required (the form's first contact, and the server refuses a client without one); Viber, WhatsApp and email are optional.
- The Update Status dialog is wider, with a group's files side by side. A slot that takes one file (quotation, job order, billing statement, invoice) says so and refuses a second; `FileUpload` has a `maxFiles` prop for it.
- **Leads page:** Date, Client, Contact (phone), Email, Actions (View; Won and Lost while idle, each with a note). View shows the lead, its history with notes and its inquiries, with Create inquiry and a group chat per inquiry.
- Staff edit on the Inquire page in edit mode, without attachments, back to the Inquiry list.

### Tests

- New `tests/Feature/Inquiry/InquiryDashboardTest.php`: the role-to-action matrix (admin included), quoting with its grouped files, quoting without the job order and uploading it later (once), the status endpoint refusing the current status, a missing note, quotation or quoted total, and a payment that does not add up; payment starting NULL and needing a billing statement; a job order never on the details page; a booking's stored instant unchanged and shown in Manila.
- New `tests/js/dashboard.test.mjs`: `formatDate`, status options, required file groups, page sizes and clamping, the pagination range text.
- Updated: `InquirySeedConsistencyTest` (payment and process no longer start together; each update's files grouped), `LeadStatusTest`, `LeadsPageTest`, `QuoteDetailsTest`, `InquiryFileUploadsTest`, `SchemaTest`, `WorkspacePagesTest`, and the schedule tests that read times (now Manila).

# Staff ask Platform or Direct before opening an inquiry; walk-in clients without an account

When marketing or sales press **Inquire**, a modal asks first whether the client has a Regray account. **Platform** ("The client has a Regray account.") finds the account and then one of its leads, as before. **Direct (walk-in)** ("The client has no account.") takes the client down, checks for a duplicate contact, and opens the inquiry with no lead and sales at `negotiation` straight away. Every client now has a record in a new `clients` table, `platform` or `direct`; there is no `walk_in` kind. The client's own Inquire flow is unchanged. Run `php artisan migrate:fresh --seed`.

The walk-in and direct clients prompt and the assign-to-account addendum this builds on were not available; the `clients` and `client_contacts` design below is drawn from this addendum alone.

## What changed

### Database

- **New `clients`** (`2026_09_29_000018_create_clients_table`): `kind` (CHECK `platform`, `direct`), `user_id` (nullable, unique, `RESTRICT`), `full_name`, `company`, `address`, `created_by`. `platform` if and only if `user_id` is set is the app's rule. An account has one record at most; records are never merged.
- **New `client_contacts`** (`2026_09_29_000035`): `kind` (CHECK `phone`, `viber`, `whatsapp`, `email`) and a normalised `value`, unique per kind and value across all clients (`idx_client_contacts_once`), which is what the duplicate check finds.
- **`inquiries`:** `client_record_id` (FK `clients`, `RESTRICT`, indexed) is the inquiry's client; `client_id` (the account) is nullable, NULL for a direct client.
- **`schedules`:** `client_id` is nullable, NULL only for a direct client's inquiry visit.

### Backend

- `ClientKind` and `ContactKind` enums; `Client` and `ClientContact` models; `ClientRepositoryInterface`; `ClientService` (`findOrCreateForUser()`, `createDirect()` with the duplicate check, `searchAccounts()`).
- Every inquiry is filed under a client record: an account's is made the first time it is needed (by the client's own inquiry or by staff on the Platform branch).
- New staff routes, guarded like the staff stepper (`inquiries.view` and `inquiries.create`): `GET inquiry/inquire/accounts` (client accounts by name or email), `POST inquiry/direct-clients` (201 created, or 200 with `existing: true` and the matched contact when the client is already on file; nothing is created then), `POST inquiry/direct-clients/{client}/inquiries` (a direct client only; a platform record is a 404 there).
- `InquiryService::createForDirectClient()`: no account, no lead, sales `negotiation` with its history row by the staff member, the visit booked by them. `SupportConversationService::cardThreadFor()` returns null for a direct client's inquiry with no group chat, so it posts no card.
- Lead options sent to the Inquire page carry `clientId`, so the Platform branch shows only the chosen account's leads that this role may use.

### Seeders

- `InquirySeeder` files every seeded inquiry under a client record (a platform record per account) and adds one direct client, Carmela Reyes of Reyes Bakeshop (phone, Viber, email), with INQ-1062 at `negotiation`: no lead, no card, its visit with no account.

### Frontend

- `StaffInquireDialog` (Platform or Direct) opens from the staff Inquire button (`useInquiryEntry`), and on the staff stepper URL when no branch is chosen. The branch and the lead are kept in the URL (`?kind=`, `?lead=`).
- `StaffInquireStart`: the Platform account search and lead step, and the Direct form (full name, company, address, contact rows) with the "already on file" offer. The stepper shows who it is for ("For Carmela Reyes · Direct (walk-in)") with "Change client", and takes no attachments.
- `lib/staff-inquire.ts` (the two options and the contact kinds) and `lib/staff-clients-api.ts`.

### Tests

- New `tests/Feature/Inquiry/StaffInquireTest.php`: account search for staff only, lead options with their client, a platform record made once, a direct client taken down and normalised, the duplicate offer, validation, a direct inquiry with no lead and sales at `negotiation`, the direct route refusing a platform record, and no `walk_in` value in the schema or anywhere in `app`, `database`, `resources/js` and `routes`.
- `SchemaTest`: client kinds, one record per account, unique contacts, a direct client's inquiry and visit with no account. `InquirySeedConsistencyTest`: every inquiry has a client record, direct ones with no lead and no card.
- `tests/js/staff-inquire.test.mjs`: exactly two options. e2e: `m-staff-modal.spec.ts` (the modal, the client never seeing it, the duplicate offer), and the staff stepper specs now enter past the modal.

# Marketing works leads; group chats belong to an inquiry

Marketing's status moves off the inquiry onto a **lead**: marketing's record of one client, created when a marketing user is given the client's Regray Team thread, with its own status (`idle`, `won`, `lost`). Every inquiry points to its client's lead. A won lead starts sales on its inquiries; a lost lead shows them cancelled. Marketing gets a **Leads** page, staff open inquiries for a lead's client, and a group chat is now made for one client's inquiry, with its status cards posted there. Clients see only the inquiry status and the payment status. Run `php artisan migrate:fresh --seed`.

## What changed

### Database

- **New `leads`** (`2026_09_29_000018`): `reference` (`LEAD-0001`, from `lead_reference_seq`), `client_id` and `marketing_user_id` (both `RESTRICT`), `status` (default `idle`, CHECK `idle`, `won`, `lost`), `status_changed_at`. Indexes on `status`, `marketing_user_id` and `client_id`, and a partial unique `idx_leads_client_open` on `client_id` where the status is `idle` or `won`: a client has one open lead at a time.
- **New `lead_status_history`** (`2026_09_29_000034`): `from_status`, `to_status` (CHECKed), `changed_by` (set null), `changed_at`; index `(lead_id, changed_at)`. Append only.
- **`inquiries`:** `marketing_status`, `marketing_status_changed_at`, their CHECK and their index are gone. `lead_id` is added (nullable, `RESTRICT`, indexed): NULL until the client's thread is claimed.
- **`inquiry_status_history`:** the `track` CHECK is `sales`, `payment`, `process`; `pending`, `won` and `lost` are gone from the status CHECKs.
- **`conversations`:** `inquiry_id` is `RESTRICT` (was set null), and unique only among groups (`idx_conversations_inquiry_group`, `WHERE type = 'group'`): an inquiry has one group chat at most.
- **`messages`:** `announced_marketing_status` is gone, with its CHECK and its place in `idx_messages_inquiry_card_once`. An inquiry card needs only `inquiry_id` and `announced_status`.
- Every change is in the table's create migration; the two new tables have their own.

### Backend

- **Enums:** new `LeadStatus`. `MarketingStatus` and `InquiryStatusTrack::Marketing` are gone. New permission `leads.view`, granted to marketing alone; `inquiries.create` is now also granted to sales.
- **`InquiryStatusCalculator`:** takes `?LeadStatus` instead of marketing. Rule 1 is cancelled when the lead is lost or sales is rejected; in SQL, an `EXISTS` on `leads`. `Inquiry::leadStatus()` reads it from a selected `lead_status` (`withLeadStatus` scope), the loaded lead or the database.
- **`LeadService`:** `assign()` (claim and reassign: creates the client's idle lead, moves an idle lead to the new holder, links unlinked inquiries), `leadForNewInquiry()` (the open lead, or a new idle lead for the thread's holder after a lost one) and `transition()` (status, history row and `LeadStatusChanged` in one transaction). The listener `ApplyLeadStatusCascade` starts sales at `negotiation` on won (through `InquiryStatusService::transition()`), and on lost cancels open visits and posts a card for each inquiry whose display status changed. The claim action runs `assign()` in the claim's transaction.
- **Creating:** a new inquiry has no role status and no history row; it links to the client's open lead, and starts sales at once on a won lead. Staff open one for a lead's client with `POST leads/{reference}/inquiries` (`inquiries.view` and `inquiries.create`, then `LeadPolicy::createInquiry`: marketing its own idle or won leads, sales won leads). Staff send no attachments; the visit is booked by the staff member. The staff `POST inquiry/inquire`, which filed the inquiry under the staff member as the client, is gone.
- **Cancelling:** unchanged for the client (only before approval). Sales started: sales `rejected`. Not started: the lead becomes `lost` (cancelling every inquiry on it). No lead yet: sales `rejected` from NULL.
- **Leads page:** `GET leads` (`leads.view`), `ViewController::leads`: tabs Idle, Won, Lost with counts; the marketing user's own leads (every lead for the admin). `PATCH leads/{reference}/status` marks an idle lead won or lost (`LeadPolicy::update`: its marketing user).
- **Group chats:** `GroupConversationService::create()` takes a client and one of that client's inquiries, which the group is bound to. Marketing picks staff from sales and technical only; every owner is always added; the creator is its admin. A sales member of an inquiry's group may add accounting and logistics users and nobody else (`GET`/`POST message/{conversation}/members`, `ConversationPolicy::addMembers`). The `TODO(inquiry-binding)` notes are resolved: cards go to the inquiry's group chat once it has one.
- **Resources:** `InquiryDetailsResource` drops `marketingStatus` and the marketing history; an inquiry cancelled by its lost lead ends its activity log with `inquiry` `cancelled`. Staff are also sent `lead` (reference, status); a client never is. Cards drop `announcedMarketingStatus`. `ConversationResource` has `canAddMembers`.

### Seeders

- **`InquirySeeder`** writes eight leads with their history (won where sales started, lost where cancelled before sales, idle otherwise), each held by `marketing@example.com`, who is given the client's Regray Team thread when the lead was created. INQ-1050 and INQ-1054 moved to Lucas Moreau, whose first lead was lost and whose next inquiry opened a new idle lead; every inquiry still shows the status it did. Three inquiries (INQ-1044, INQ-1046, INQ-1049) have a group chat staffed by the rules above, with accounting and logistics added later on the two approved ones; their cards go to the group from when it was made.
- `E2EMessageSeeder` and `ConversationSeeder` no longer write marketing status; `ConversationSeeder` leaves a thread InquirySeeder already assigned as it is.

### Frontend

- **`pages/leads.tsx`:** tabs with counts; each row has the reference, client, linked inquiries, lead badge and updated date, and Mark won, Mark lost (with a confirmation), Create inquiry and a group chat per inquiry without one. **Leads** is in the sidebar for `leads.view`.
- **Staff Inquire:** picks the lead first (`?lead=` in the URL), then the usual steps, without attachments, sent under the lead.
- **Group form:** client, then one of the client's inquiries (those with a group are disabled), then staff; owners are listed as always included. Sales members of an inquiry's group get an "Add accounting or logistics" control in the thread header.
- `lib/lead-statuses.ts` and `LeadStatusBadge`; marketing is gone from `lib/inquiry-statuses.ts`, the badge, the types and the mock data.

### Tests

- New `tests/Feature/Lead/LeadStatusTest.php` and `LeadsPageTest.php`: lead on claim and on reassignment, won starting sales, lost cancelling inquiries and visits, client cancel before sales marking the lead lost, a new lead after a lost one, page access by role, and staff inquiry creation by role.
- `GroupConversationTest`: sales and technical only for marketing, owners always added, inquiry of the chosen client, one group per inquiry, cards into the group, later additions by sales.
- `InquiryStatusCalculatorTest` covers lead NULL, idle, won and lost, PHP against SQL on all 576 combinations. `SchemaTest`, `InquirySeedConsistencyTest`, the card, Quote, service and repository tests, `tests/js/inquiry-statuses.test.mjs` and the e2e staff Inquire specs follow the new rules.

# A role's status exists only once its stage has started

Sales, payment and logistics are no longer set from the moment an inquiry is created. Each is NULL until its stage starts, and nothing is shown for it until then. Marketing starts at creation (`pending`). Sales starts when marketing is won (`negotiation`). Accounting and logistics start together when the client approves the quotation, which sets sales to `quoted`: payment at `awaiting`, process at `received`. Until approval an inquiry shows only marketing and sales. The flow that starts the stages is written down (`docs/database/regray-erd.md`, section 4) but not built. Run `php artisan migrate:fresh --seed`.

## What changed

### Database

- **`inquiries`:** `sales_status`, `payment_status` and `process_status` are nullable with no default, and so are their `*_changed_at`. Their value CHECKs stay and pass a NULL. `marketing_status` is unchanged: `NOT NULL`, default `pending`. No CHECK ties payment and process together; the app sets them together.
- **`inquiry_status_history`:** an inquiry's first entry is marketing `pending` only. Sales, payment and process begin, from NULL, when their stage starts.
- **`messages`:** an `inquiry_card` needs `inquiry_id`, `announced_status` and `announced_marketing_status`; the sales, payment and process snapshot columns are NULL until their stage started. `idx_messages_inquiry_card_once` reads those three through `COALESCE(column, '')`, so a snapshot with NULLs is still posted once.

### Backend

- **`InquiryStatusCalculator`:** takes a nullable sales and process status. The logistics status shows once the job order is in and process is set; the sales status shows once sales is set (no longer "once marketing is won"). The SQL `CASE` treats NULL the same way.
- **Creating:** a new inquiry sets marketing only, with one history row.
- **Cancelling:** the client can cancel only before approving the quotation. It marks sales `rejected` once sales has started, and marketing `lost` before it has; open schedules are cancelled either way. From sales `quoted` on, the client's Cancel is shown locked ("You approved the quotation, so only the team can cancel now. Message the team.", `InquiryActions::CANCEL_LOCKED_WHEN_QUOTED`) and the endpoint refuses it with the same sentence: only sales can cancel then, by rejecting, which shows the inquiry as cancelled. The sales reject action itself is not built.
- **Resources:** the details page and the thread card send `null` for a stage not started, never a placeholder. The Quote card sends `paymentStatus` as stored, which is null until approval. `InquiryDetailsResource` sends the client's activity log (sales, payment and logistics changes, a stage starting included; marketing left out except a cancel) and `documents`: the quotations (with their revision) and payment receipts the team has shared, oldest first, without paths. The job order is internal and never sent.
- `createInquiryCardOnce()` and `PostInquiryStatusCard` take and post NULL snapshot columns.

### Seeders

- **`InquirySeeder`** sets each role status only when its stage starts, in history, inquiry and cards alike, and refuses a sales event before sales has started. Every reference and every display status is unchanged; only the NULLs are new (see the inquiry list in the report).
- `E2EMessageSeeder` writes NULL for stages not started in its card snapshots and inquiries.

### Frontend

- `StatusBadge` takes `null` and renders nothing. The group is named in its tooltip.
- `lib/inquiry-statuses.ts`: `startedStatuses()` leaves out every stage not started.
- **Details page:** two badges at most: the inquiry status, and the payment status once it is set. The aside has the **Activity Log** in the client's words ("Inquiry sent", "In discussion", "Quote approved", "Awaiting payment", "Partially paid", "In production"…), newest first, with the date always on the right, and below it **Documents**: each quotation ("Quotation · Rev 2") and payment receipt, with its file name, size and date. The wording is `activityLabel()` and `documentLabel()` in `lib/inquiry-statuses.ts`.
- **Quote list and thread card:** the inquiry status, then the payment badge only when payment is set.

### Tests

- `InquiryStatusCalculatorTest`: the NULL cases, and PHP and SQL agreeing on all 432 combinations, NULL included.
- `SchemaTest`: NULL accepted for sales, payment and process; invalid values still refused; marketing defaults to `pending`; an inquiry card needs its marketing status; a snapshot with NULLs is posted once.
- `InquirySeedConsistencyTest`: no role status before its stage started, payment and process both NULL or both set, each history track starting with its stage and ending on its current value, no duplicate snapshots.
- `tests/js/inquiry-statuses.test.mjs`: a null payment, sales or logistics status gives no badge.
- Cancel, card, details and creation tests follow the new rules.

# One inquiry is one project, with four role statuses and one display status

An inquiry is now one project: the project level (`inquiry_projects`) is gone and its columns are on `inquiries`. A client who wants another project opens another inquiry, and the Inquire page opens one inquiry per project it sends. Each inquiry has four role statuses (marketing, sales, accounting's payment, logistics' process), all set from the moment it is created, and one display status, the inquiry status, which is the single status shown for it everywhere. Sales uploads quotations and the job order, accounting uploads receipts. The Quote list and the details page are reworked. The flow between the statuses is written down (`docs/database/regray-erd.md`, section 4) but not built: only the client's cancel moves a status. Run `php artisan migrate:fresh --seed`.

## What changed

### Database

- **`inquiries`** loses `status` and `status_changed_at` and gains the project's `name`, `location`, `landmark`, `area_sqm` and `notes`; `marketing_status` (default `pending`), `sales_status` (default `negotiation`), `payment_status` (default `awaiting`) and `process_status` (default `received`), each `NOT NULL` with its own value CHECK and `*_changed_at`; and `quoted_total` (`> 0`) and `quote_revision` (`>= 1`). Indexes on `client_id` and each status.
- **Renamed and re-keyed on `inquiry_id`:** `inquiry_services`, `inquiry_categories`, `inquiry_stones` (still unique on stone, edge and finish). `inquiry_files` is re-keyed too and gains `document_type` (`client_attachment`, `quotation`, `receipt`, `job_order`) and `revision` (`>= 1`).
- **`inquiry_status_history`** gains `track` (`marketing`, `sales`, `payment`, `process`). `to_status` and `from_status` take any role value, with no link to the track; `draft` is gone. Each inquiry's first four entries are the defaults, from nothing.
- **`schedules`:** partial unique `inquiry_id WHERE inquiry_id IS NOT NULL`, one deadline visit per inquiry.
- **`messages`:** the `project_card` type and `inquiry_project_id` are gone. An `inquiry_card` carries `announced_status` (a display status) and `announced_marketing_status`, `announced_sales_status`, `announced_payment_status`, `announced_process_status`, each with a value CHECK; every other type leaves them NULL. `idx_messages_inquiry_card_once` is unique on the whole snapshot.
- **Removed:** the `inquiry_projects`, `quotations`, `job_orders` and `payments` create migrations. `payment_transactions` is recreated as `2026_09_29_000033`, keyed on `inquiry_id`.
- Only single-column value and range CHECKs were added; nothing ties one status to another. Every change is in the original create migration of its table.

### Backend

- **Enums:** `InquiryStatus` is the display status (16 values) with `group()` for the Quote tabs (`InquiryStatusGroup`: pending, in progress, closed, cancelled). New `MarketingStatus`, `SalesStatus`, `PaymentStatus` (without `refunded`), `ProcessStatus`, `InquiryStatusTrack` and `DocumentType`. `InquiryProjectStatus`, `QuotationStatus`, `JobOrderStatus` and `MessageType::ProjectCard` are gone.
- **`InquiryStatusCalculator`:** the display rule in PHP (`calculate()`) and as a SQL `CASE` (`sql()`). `Inquiry` reads it as `$inquiry->status` and has `withDisplayStatus`, `whereDisplayStatus`, `inStatusGroup` and `withJobOrderFlag` scopes. A removed job order file does not count.
- **Models:** `Inquiry` holds the project's relations (`services`, `categories`, `stones`, `files`, `schedule`, `paymentTransactions`). `InquiryProject`, `Quotation`, `JobOrder` and `Payment` are gone; `InquiryProjectStone` is `InquiryStone`.
- **`InquiryFileUploads`:** who uploads which document (the inquiry's client, sales, accounting). The client's own uploads go through it.
- **`InquiryStatusService::transition()`** takes a track, and `InquiryStatusChanged` carries it. A client's cancel sets sales to `rejected` and cancels the open schedule.
- **`InquiryActions`** reads the inquiry: Update is open until the client approves the quotation (sales quoted), the invoice from approval on, Cancel until closed or cancelled. `UPDATE_LOCKED_WHEN_ONGOING` is `UPDATE_LOCKED_WHEN_QUOTED` again and `UPDATE_LOCKED_WHEN_LOST` is `UPDATE_LOCKED_WHEN_CANCELLED`; their wording is unchanged.
- **Creating and editing:** `InquiryService::create()` returns one inquiry per project sent, and the success flash names every reference. Update on a Quote card opens the inquiry's project at the first step (Services) with every answer filled in, instead of on Review; Review is still where Save changes is. The edit still sends one project, which must be this inquiry, and only ever keeps or drops client attachments. `ScheduleService::createForInquiryProject` is `createForInquiry`.
- **Cards:** `EloquentMessageRepository::createInquiryCardOnce()` takes the snapshot (and an optional time) and inserts it once. `PostInquiryStatusCard` posts the inquiry as it is. `InquiryCardResource` sends the project name and location, the announced snapshot and the current display status.
- **Resources:** the Quote card sends `status` and `paymentStatus`, which is null until the client has approved the quotation (sales quoted). `InquiryDetailsResource` sends one flat `project`, `status`, `marketingStatus`, `salesStatus`, `paymentStatus` and `processStatus`, and `history`: every role status change (track, status, time), oldest first, without the four starting defaults and without who made it. `statusHistory` is no longer sent.

### Seeders

- **`InquirySeeder`** throws in production. It replays each inquiry's events against the flow before writing (a step out of order, payments that do not add up, or an inquiry not ending at the status its data says it shows stops it), then writes the inquiry, handlers, schedule, marketing and sales history, quotation files, the approval, the job order, payments with receipts, and logistics history, every file through `InquiryFileUploads`. One inquiry card per distinct snapshot goes into the client's Regray Team thread, each client's in time order.
- **`data/inquiries.php`:** 21 inquiries. Every existing reference, client, handler and attachment is kept; the second projects of INQ-1048, INQ-1041 and INQ-1046 are now INQ-1049, INQ-1042 and INQ-1047, and INQ-1050 to INQ-1061 are new. Between them every display status and every payment status is shown. `inquiry_reference_seq` is set past the seeded references.
- `E2EMessageSeeder` writes one card per display status with a matching snapshot. `ConversationSeeder` no longer writes inquiry cards.

### Frontend

- `lib/inquiry-statuses.ts` (new, import-free): the status types, a label map for every display, marketing, sales, payment and logistics value, and the badge treatment (solid for reached states, outline for open ones, secondary for stopped ones).
- `components/inquiry-status-badge.tsx`: one `StatusBadge` read in its group (`inquiry`, `marketing`, `sales`, `payment`, `process`).
- **Quote list:** each card shows the inquiry status, and the payment status beside it once the client has approved the quotation (sales quoted). The tabs are All, Pending, In progress, Closed, Cancelled (`QUOTE_STATUS_TABS` in `lib/quote-filters.ts`).
- **Details page:** the Status card, the collapsing, "Project N" and the "N projects" line are gone. The header has the reference, the inquiry status badge and, once sales is quoted, the payment status badge, with no captions. The project's name heads its section. The side column is an **Activity Log**: every marketing, sales, payment and logistics change ("Sales · Site visited"), newest first, ending on "Inquiry sent".
- **Thread card:** the sub-line is the project name and the status row is two badges, the announced display status and payment status. The project card is gone.
- `data/inquiries.ts`, `types/quote.ts`, `types/message.ts`, `lib/thread-cards.ts` and `lib/message-grouping.ts` use the new shape.

### Tests

- `SchemaTest`: every value CHECK and unique index refuses a bad row, no CHECK ties statuses together, and each `messages` type rule still holds.
- `InquiryStatusCalculatorTest`: every precedence case, and PHP and SQL agreeing on all 330 combinations. `InquiryFileUploadsTest`: quotations and job orders by sales only, receipts by accounting only.
- `InquirySeedConsistencyTest`: display statuses, PHP and SQL alike; payments against the payment status; each history track; documents and their uploaders in flow order; one card per snapshot; the coverage list; and the production guards.
- Existing tests, `tests/js` and the Playwright specs use the new statuses and the single project. `ProjectCardTest` is folded into `InquiryStatusCardTest`.

# Inquiries broken down into projects: quotations, job orders and payments

An inquiry's projects (`inquiry_projects`) now each have a status, quotations (one row per revision), job orders and a payment with its transactions. The inquiry's status is read from its projects'. The seeded demo inquiries carry realistic data for all of it, and a project card shows a project's snapshot in the thread. The rules that move statuses along are written down (`docs/database/regray-erd.md`, section 4) but not built: nothing changes a status by them yet. Run `php artisan migrate:fresh --seed`.

## What changed

### Database

- **Statuses:** `inquiries.status` is `pending`, `ongoing`, `closed` or `lost`. `completed` became `closed` and `cancelled` became `lost`; `quoted` and `paid` are gone. `inquiry_status_history` has a CHECK on the same list plus `draft`, which stays history-only.
- **`inquiry_projects`:** `status` (`pending`, `ongoing`, `closed`, `cancelled`), `status_changed_at`, and an index on `(inquiry_id, status)`. A new project starts `pending`.
- **New tables**, one create migration each (`2026_09_29_000033` to `000036`):
    - `quotations`: unique `(inquiry_project_id, revision_no)`, and one live quotation (pending, closeable or closed) per project (`idx_quotations_one_live`).
    - `job_orders`: the reference comes from `job_order_reference_seq`, like inquiry references.
    - `payments`: one per quotation, and the status has to agree with the amounts (awaiting has nothing paid, partial some, fully paid all of it).
    - `payment_transactions`: the deposits and refunds behind a payment. `amount_paid` is the sum of its `payment` rows.
- **`messages`:** `type = 'project_card'` with `inquiry_project_id`, `announced_status` (the project's), `announced_quotation_status` and `announced_payment_status`. Every other type has to leave the three new columns NULL. `idx_messages_project_card_once` keeps a snapshot to one card per conversation and project.
- Every change is in the original create migration of its table.

### Backend

- **Enums:** `InquiryStatus` is `Pending`, `Ongoing`, `Closed`, `Lost` plus `Draft`. New `InquiryProjectStatus`, `QuotationStatus`, `JobOrderStatus`, `PaymentStatus`, and `MessageType::ProjectCard`.
- **Models:** `Quotation`, `JobOrder`, `Payment`, `PaymentTransaction`. `InquiryProject` has a status cast and `quotations()`, `jobOrders()` and `payments()`; `Message` has `inquiryProject()`.
- **Renames, not redesigns:** `InquiryActions` and `InquiryStatusService` read the new names. A client's cancel makes the inquiry `lost`. Ongoing takes the place of both quoted and paid, so it keeps quoted's locked Update and Cancel and paid's invoice. `UPDATE_LOCKED_WHEN_QUOTED` is now `UPDATE_LOCKED_WHEN_ONGOING`, and `UPDATE_LOCKED_WHEN_CANCELLED` is now `UPDATE_LOCKED_WHEN_LOST`; their wording is unchanged. The cancel refusal now reads "Only pending or ongoing inquiries can be cancelled."
- **Project card:** `EloquentMessageRepository::createProjectCardOnce()` posts a snapshot once (`insertOrIgnore`, like `createInquiryCardOnce`), at an optional time. `Message\ProjectCardResource` sends the inquiry reference, project name and location, and the three statuses; `MessageResource` sends it as `projectCard`. `ConversationResource` previews it as "Project {name} · {Status}". The project and its inquiry are eager-loaded wherever cards are loaded. No listener posts project cards yet; that comes with the status flow.

### Seeders

- **`InquirySeeder`** throws in production, like `DemoSeeder`. It writes, per inquiry, the inquiry, handlers, projects (booked through `ScheduleService`), quotations, job orders and payments with their transactions, the status history, then the project cards into the client's Regray Team thread (`SupportConversationService::cardThreadFor`), oldest first.
- **`data/inquiries.php`** keeps every reference, client, handler and attachment, and adds INQ-1046 for the one demo client without an inquiry. It covers a pending project, a project on a second revision, a closeable quotation with a partial payment and a job order for install, a job order on hold, projects cancelled by a rejected quotation, a refunded payment and a cancelled job order, closed projects, and a lost, a closed and an ongoing inquiry.
- The inquiry id sequence is reset as before, and `job_order_reference_seq` is set past the seeded references.
- `ConversationSeeder` gives Rafael's thread its sample conversation when it has no text yet. The project cards are already there.

### Frontend

- `components/conversations/project-card.tsx`: the inquiry card's layout, with the project name over the strip, "INQ-… · location" under it, a project status badge, and the quotation and payment statuses.
- `thread.tsx`, `lib/message-grouping.ts` and `types/message.ts` handle `project_card`. `lib/thread-cards.ts` has `projectHeadline()` ("Project ongoing"), `quotationStatusText()` and `paymentStatusText()`.

### Tests

- `tests/Feature/Database/SchemaTest.php`: every new CHECK and unique index refuses a bad row.
- `tests/Feature/Database/InquirySeedConsistencyTest.php`: after `DatabaseSeeder`, every stored status matches the status derived from the rules, payments add up, histories end on the inquiry's status, job orders and payments sit on a closeable or closed quotation, timestamps run in order, and no project card snapshot repeats. The production guards are tested as well.
- `tests/Feature/Message/ProjectCardTest.php`: posted once, shown to the client and staff, previewed in the list. `tests/js/thread-cards.test.mjs` covers the new helpers.
- Existing tests use the new names.

# Book an appointment: bookings are saved and announced

A client picks a day and a slot, enters a purpose and submits. A pending appointment is saved under the purpose, shows on their Schedule page and in Upcoming, and a "Booking pending" card is posted to their Regray Team thread. "Book a visit" is now "Book an appointment" in the booking flow.

## What changed

### Backend

- **Route:** `POST schedule/book` (`schedule.book.store`), guarded by `schedule.create`. Both Book an appointment pages (under Schedule and under Profile) post here. `BookingController::store` follows `InquiryController::store`: it flashes `booking.status` (`success`, or `error` with a message after the failure is reported) and redirects back.
- **`StoreBookingRequest`:** `date` (`Y-m-d`, not before today in Manila), `arrivalTime` (`H:i`, one of the 30-minute slots from 10:00 to 18:00, `StoreBookingRequest::slots()`), and the arrival must still be ahead of now. `purpose` is required, trimmed, at most 80 characters; `notes` is optional, trimmed, at most 500. Any client or user id in the payload is ignored.
- **`ScheduleService::createForBooking()`:** `client_id` and `booked_by` are the signed-in client, `booked_by_type` is `client`, there is no inquiry, the status is `pending`, the purpose is the title, and the Manila arrival is stored in the app's zone (UTC).
- **Schedule card:** `BookScheduleAction` saves the booking and dispatches `ScheduleBooked` (after commit). The queued `PostScheduleCard` listener posts a `schedule_card` with `announced_status = pending` through `EloquentMessageRepository::createScheduleCardOnce()`, then touches the conversation, broadcasts `MessageCreated` and announces the activity. The thread is picked in one place, `SupportConversationService::scheduleCardThreadFor()`: the client's Regray Team thread, created if missing. The group-chat rule goes there later. The listener's `post()` takes the status to announce, so status-change cards can reuse it.
- **Index:** `idx_messages_schedule_card_once`, unique on `(conversation_id, schedule_id, announced_status) WHERE type = 'schedule_card'`. It is in the original `create_messages_table` migration, next to the inquiry-card one, to keep one migration per table. Run `php artisan migrate:fresh --seed`.
- `ViewController::scheduleBook` passes `studioNow` (Manila's date and time), so the page decides which days and slots are past by the studio's clock.
- **Staff booking is deferred:** the controller's staff branch, the page's client picker and the staff tests are commented out under `TODO(staff-booking)`. A staff post saves nothing for now.

### Frontend

- `pages/schedule-book.tsx` submits with `router.post` and maps the 422 errors onto the fields. A server failure opens the existing error dialog, and Try again resends. The fake `submitBooking()`, its timer and the `simulate=error` hook are gone.
- **`lib/booking-slots.ts`** (new, import-free): `bookingSlots()` gives `HH:mm` values, `slotLabel()` shows them as 12-hour times, and `openSlotsOn(day, studioNow)` gives the slots still ahead. `lib/booking-schema.ts` keeps only the form schema.
- **Wording:** the page title, heading and button, the success and error dialogs, the Schedule header button and description, the Profile row, and the schedule card headlines ("Appointment completed", "Appointment booked", fallback "Appointment") in `ConversationResource` and `lib/thread-cards.ts`.

### Tests

- `tests/Feature/Schedule/BookingStoreTest.php`: the saved row and the ignored client id, UTC storage, the schedule and Upcoming, the card posted once, every rejection, a failed write, and guests.
- `tests/js/booking-slots.test.mjs`. `tests/js/thread-cards.test.mjs` and `tests/e2e/message/d-cards.spec.ts` use the new headlines.

The `TODO(booking)` markers are removed.

# Services: selection groups

Each service now has a `selection_group` (string, in the original `create_services_table` migration). Services can share a project only when they share a group. The groups live in `database/seeders/data/services.php`: Stone Relocation, Dismantling & Reinstallation is in `stone-relocation`, and every other service is in `stone-work`. To change which services combine, edit the group there (or in the database). No code change is needed.

- **Backend:** `InquiryServiceResource` sends `group`. `StoreInquiryRequest` (and so `UpdateInquiryRequest`) refuses a project whose services span more than one group, at `projects.N.serviceIds`: "These services cannot be in the same project."
- **Frontend:** `canPickService()` in `lib/inquiry-catalog.ts`. On the Services step, a service from another group than the ones picked is disabled, with a note: "Some services can't share a project with the ones you picked. Add them as another project." Unpicking everything frees all services again.
- **Tests:** `StoreInquiryRequestTest` (mixed groups refused, same group accepted), `InquiryControllerTest` (the payload has `group`), `tests/js/service-groups.test.mjs`.

Run `php artisan migrate:fresh --seed`.

# Services: Site Ocular and Post-Installation Quality Care removed

Site Ocular & Technical Assessment and Post-Installation Quality Care are no longer offered. They are taken out of the service catalogue (`database/seeders/data/services.php`, `resources/js/data/services.ts`) and out of the seeded and mock inquiries that listed them. Stone Relocation, Dismantling & Reinstallation is now the only standalone service, so it is the one that asks for a needed-by time. Run `php artisan migrate:fresh --seed` to pick it up. Tests that used Site Ocular as their visit now use Stone Relocation, and e2e test 19 adds three named stone projects instead of three different visits.

# Quote "Update": edit a sent inquiry from the Review step

Update on a Quote card now opens the Inquire page at Review with that inquiry's projects. The client opens a project with Edit, changes it, comes back to Review and presses Save changes, which updates the same inquiry. No schema change.

## What changed

### Backend

- **Routes** (client-only, guarded by `quotations.view` and `inquiries.create`, registered above `quote/{reference}`):
    - `GET quote/{reference}/edit` (`quote.edit`) renders `inquire` with `mode: 'edit'`, `reference`, `projects` (`InquiryEditResource`) and the same catalogue as the Inquire page (`InquiryCatalogResource`, now shared with `ViewController::inquire`).
    - `PATCH quote/{reference}` (`quote.update`).
- **`InquiryEditController`**: `edit` → `ShowInquiryEditAction` (scoped through `InquiryDetailsService::forClient`, so another client's reference is a 404). When the status may not be updated, it redirects to `quote/{reference}` with an error toast carrying the reason.
- **`InquiryActions::updateRefusal()`** gives the reason, with `UPDATE_NOT_AVAILABLE` ("This inquiry can no longer be changed.") for paid and completed, where the button is hidden and so has no reason of its own.
- **`InquiryEditResource`**: real ids for projects and files, service and application slugs, stones as `{stoneId (slug), quantity, edge, finish}`, the site answers, `neededBy`/`neededByTime` from the schedule in Manila time (time only for visits), `deadlineLocked`/`deadlineLockedReason`, and `attachments` with `status: 'saved'`.
- **`UpdateInquiryRequest`** extends `StoreInquiryRequest`, so every create rule applies, and adds `projects.*.id` and `projects.*.keepFileIds` (kept plus new files may not exceed the per-project limit).
- **`UpdateInquiryAction` → `InquiryService::update()`**, in one transaction:
    - locks the inquiry row and asks `updateRefusal()` again at write time (a status that changed since the page loaded is refused as a `status` error);
    - the projects sent must be exactly the inquiry's projects, by id (anything else is refused as a `projects` error);
    - updates each project's fields and syncs its services, applications and stones;
    - moves the project's visit through `ScheduleService::updateForInquiryProject` (title, notes, arrival), and books one if an older project had none;
    - deletes the file rows not kept and stores new uploads with the `WrittenFiles` cleanup; the removed files are deleted from their disk only after the commit;
    - writes one `AuditEvent::Updated` row (new `AuditLogRepositoryInterface`), with each project's editable fields before and after. The status and the status history are untouched.
- **`ScheduleService`**: `arrivalForInquiryProject()` (a visit's own time, otherwise the time of day the schedule already has), `deadlineLockedReason()` (null only while the visit is `pending`) and `updateForInquiryProject()`.

### Frontend

- `pages/quote.tsx`: Update links to `quote.edit`; View details still goes to the details page.
- `pages/inquire.tsx` takes `mode`. In edit mode it:
    - starts on Review, with the form filled by `toEditFormProjects()`;
    - is titled "Update {reference}", with "Back to Quote" in the header, which returns to the Quote list;
    - hides Add and Remove and labels the send "Save changes" ("Nothing is saved until you save changes"), from `reviewActions()`;
    - saves with `sendInquiryRequest(url, values, 'patch')`, a POST with `_method=PATCH` carrying `projects[i][id]` and `projects[i][keepFileIds][k]`; errors go back on their projects through `mapInquiryErrors`, and success lands on the details page with the server's toast;
    - shows a locked deadline read-only with its reason in the requirements step;
    - refuses a commit that would split the project into several (picking a site visit beside a stone, or two visits).
- **Attachments** are now a union: an upload (`File`, `uploading`/`done`) or a stored file (`status: 'saved'`, `fileId`, name, size, type). `attachmentMeta()` reads either; the uploader, the review sheet, the schema and `buildInquiryFormData` handle both.

## Decisions

- **Drafts are off in edit mode.** No `edit:{reference}` key: the edit never reads, writes or clears the new-inquiry draft (`isRestored` stays false, so the autosave is inert). A refresh starts the edit over from the server.
- **Add and Remove stay out.** Editing changes existing projects only, and a commit that would add projects is refused in the browser and on the server. Adding or removing projects on a sent inquiry could come back later; it needs its own rules for schedules and history.
- **Locked deadline:** once a project's visit is no longer `pending` (the prompt said `requested`, which is now `pending`), its date and time are shown read-only with the reason, and the server refuses a change but accepts the same value back, so the rest of the project can still be edited.
- **Unchanged deadlines are still checked.** A deadline already in the past fails the "not in the past" rule on save, as it does for a new inquiry, so the client has to pick a new date. A locked deadline that is already in the past therefore cannot be saved; in practice the inquiry is quoted by then.
- **A project that is not a visit** keeps its schedule's time of day when only the date changes.

## Tests

- **Pest, `Quote/QuoteEditTest`:** the edit page for a client's own pending inquiry with real ids; quoted, paid, completed and cancelled redirect with the reason; another client's inquiry is a 404; Sales and Marketing get 403 on both routes; a save changes fields, replaces stones and services, moves the visit, writes an audit row and no status history; a confirmed visit cannot move but the rest saves; a project id from another inquiry is refused; removed files are deleted and new ones stored; a failure partway leaves nothing changed and no new files; a status quoted after the page loaded is refused.
- **node:test, `tests/js/inquiry-edit.test.mjs`:** `toEditFormProjects`, `deadlineLocks`, `reviewActions` in both modes, and `buildInquiryFormData` with ids and kept files.
- **Playwright, `inquiry/l-edit.spec.ts` (42):** Update → Review lists the project with no Add or Remove → Edit → rename → Back to Review → Save changes → the details page shows the new name.

# Schedule page on real data, and inquiry schedules

Branch `dev`. The Schedule page reads real schedules, and sending an inquiry books one schedule per project.

## What changed

### Data (migrations consolidated)

Still in development, so the migrations were rewritten rather than added to. Run `migrate:fresh --seed`.

- **One migration per table, and no altering migrations.** Every column, index, check constraint and foreign key of a table is in its create migration:
    - `users` has the two-factor columns (was `2025_08_14_…_add_two_factor_columns_to_users_table`).
    - `conversations` has `support_client_id`, `assigned_to`, `assigned_at` (with their checks and the `assigned_at` trigger) and `pool_notified_message_id`.
    - `messages` has `reply_to_id`, `client_key`, `system_event`, `system_user_id`, the `system` type check, the inquiry-card-once index and the `schedule_id` foreign key.
    - `conversation_members` has `role` and the read, delivered and notified message pointers with their foreign keys.
    - `stone_sizes`, `stone_thicknesses`, `stone_stone_category`, `stone_stone_size` and `stone_stone_thickness` each have their own file (was `create_stone_filter_tables`). The default sizes and thicknesses are still inserted; the backfill from existing stones is dropped, as a fresh database has none.
    - Removed: `000034_add_last_read_message_fk_to_conversation_members`, `000035_add_schedule_id_fk_to_messages`, the three `2026_10_20_…` migrations and `2026_10_21_…_add_reminder_markers_to_conversations_and_members`.
- **Renumbered so each table comes after the tables it points at:** `schedules` is now `000022` (before `inquiry_projects`, `000023`, which constrains `schedule_id` directly), and `messages` (`000030`) comes before `conversation_members` (`000031`). The tables in between moved up by one.
- **The one exception:** `conversations` and `messages` point at each other, so `conversations.pool_notified_message_id`'s foreign key is added at the end of the messages migration, once both tables exist.
- **Left grouped:** the files Laravel and Spatie ship (`users` with `password_reset_tokens` and `sessions`, `cache`, `jobs`, and the permission tables).
- `inquiry_projects` has no `deadline_date` or `deadline_time`; it has `schedule_id` (nullable, unique, `nullOnDelete`).
- `config/database.php`: the `pgsql` connection now sets the session `timezone` (`DB_TIMEZONE`, default `UTC`). Eloquent writes timestamps without an offset in the app's zone, so a Postgres server in another zone (the local one runs `Asia/Shanghai`) stored every timestamp 8 hours off. Production's Postgres image already runs in UTC.

### Backend

- **Schedule page:** `ScheduleIndexRequest` → `ListSchedulesAction` → `ScheduleService` → `ScheduleRepositoryInterface` / `EloquentScheduleRepository` (bound in `AppServiceProvider`) → `ScheduleResource` (`id`, `title`, `arrivalAt`, `status`, `notes`, `inquiryReference`; nothing about the client).
    - `ViewController::schedule()` serves `/schedule` and `/profile/schedule` with two lazy props: `schedules` (every status, `arrival_at` in `[from, to)`, by arrival) and `upcoming`.
    - `from`/`to` are ISO dates or datetimes; a value without an offset is read in Manila. Both or neither; `to` after `from`; at most 62 days (`schedule.max_range_days`). Without them the range is the six weeks the mini calendar shows for this month.
    - Visibility comes from the signed-in user: `isClient()` filters to `client_id = user`; every staff role sees all. A `client_id` in the URL is ignored.
    - **Upcoming:** the next three (`ListSchedulesAction::UPCOMING_LIMIT`) `pending` or `confirmed` visits with `arrival_at >= now - schedule.ongoing_minutes` (default 60), soonest first. Empty when there are none.
    - New `config/schedule.php`: `timezone` (Asia/Manila), `ongoing_minutes` (`SCHEDULE_ONGOING_MINUTES`) and `max_range_days`.
- **Inquiries:**
    - `InquiryService::addProject()` books the project's schedule through `ScheduleService::createForInquiryProject()` (client as `client_id` and `booked_by`, `inquiry_id`, `pending`, title = project name, notes "Inquiry {reference} · {project name} · {location}", so a visit says which of the inquiry's projects it is for), then writes the project with `schedule_id`. Both are inside `CreateInquiryAction`'s transaction.
    - `InquiryStatusService::transition()` to `cancelled` cancels the inquiry's pending and confirmed schedules in the same transaction (`cancelled_at` now, reason "Inquiry cancelled"). Completed ones are left alone.
    - `InquiryDetailsResource` reads `neededBy` and `neededByTime` ("Target deadline") from the linked schedule in Manila time.
    - `StoreInquiryRequest`: `neededBy` (the date) is required for every project. `neededByTime` is asked and required only when every service on the project is standalone (Site Ocular & Technical Assessment; Stone Relocation, Dismantling & Reinstallation; Post-Installation Quality Care), and must still be ahead in Manila time. Any other project's time is dropped and its schedule is booked for 10:00, the showroom's opening hour (`ScheduleService::DEFAULT_ARRIVAL_TIME`).
    - `InquiryDetailsResource` returns `neededByTime` only for those visit projects, so the details page never shows the default 10:00.
    - **Inquiry card in a thread:** the bold heading is the inquiry reference instead of the first project's name, and the line under it is the project count only ("2 projects"). `InquiryCardResource` no longer sends `title`.

### Frontend

- `pages/schedule.tsx` draws the `schedules` and `upcoming` props. Previous, Next, Today, the mini calendar's day and month, and the mobile day step call `router.get` with `only: ['schedules']`, `preserveState`, `preserveScroll` and `replace`, passing one range that covers the grid and the mini calendar's six weeks. When the two drift further apart than 62 days, the side that did not move follows the one that did. The grid dims and is `aria-busy` while loading.
- `components/schedule/upcoming-list.tsx` is one flat list of the `upcoming` prop, with no day headings ("Today", "Tomorrow"). Each row has a month-and-day tile, the title, the weekday and time, the status and the actions menu.
- **Week grid hours:** the grid shows 10:00–18:00 and widens to take in any visit on show outside those hours (`gridHours()` in `lib/schedule.ts`). Before, such a visit was silently left off the grid, which is why a project booked for the old 09:00 default did not appear.
- The event dialog shows the inquiry reference when there is one.
- The mock `data/schedule.ts` is removed. The types and the pure helpers (`toScheduleItem`, `rangeCovering`, `fitsRange`, `rangeQuery`, `gridHours`) are in `lib/schedule.ts`.
- **Inquire page:** every project asks for a date ("Target deadline"), required. Only a visit also asks for the time ("… at …"). `lib/inquiry-schema.ts` mirrors the PHP rule.
- `components/conversations/inquiry-card.tsx` shows the reference as the heading and `projectCountText()` (`lib/thread-cards.ts`, replacing `inquirySubline`) under it.
- `pages/schedule-book.tsx`: comment only (see TODO). Booking is unchanged.

### Seeders

- `InquirySeeder` books each project through `ScheduleService::createForInquiryProject()` and cancels a cancelled inquiry's schedules through `ScheduleService::cancelForInquiry()`. Every project in `seeders/data/inquiries.php` now has a date and a time.
- `ScheduleSeeder` no longer maps `apt-03`, `apt-04` and `apt-06` to inquiries. Its booking rows keep `inquiry_id` NULL and are looked up with it, so they never match an inquiry's schedule.

## Decisions

- **Date required for every project, time only for visits:** `arrival_at` is NOT NULL and every project becomes a schedule, so every project needs a date. A project that is not a visit is booked for 10:00 Manila (opening time) on that date. Changed in `StoreInquiryRequest` and `lib/inquiry-schema.ts` together.
- **Schedule status `requested` renamed to `pending`:** a schedule waits as `pending` until Marketing or Sales approve it. Statuses are `pending`, `confirmed`, `completed`, `cancelled`; the chat card reads "Booking pending". The approval step itself is not built yet.
- **"Now" window:** a visit counts as happening now for 60 minutes after its arrival (`schedule.ongoing_minutes`).
- **Cancelling an inquiry cancels its schedules** (pending and confirmed only), in the same transaction. `InquirySeeder` applies the same rule to seeded cancelled inquiries.
- **Range rule on the page:** one request covers the visible days and the mini calendar's six weeks, capped at 62 days.

## TODO list

- **`TODO(booking)`:** unchanged, plus: a booking creates a schedule with `client_id` = the client, `booked_by` = the client, `booked_by_type` = client, `inquiry_id` = NULL, status `pending`, and is never linked to an inquiry project. Marked in `Schedule` and `pages/schedule-book.tsx`.
- There are still two `schedule-status-badge.tsx` files (`components/` and `components/schedule/`). Not merged here.

## Tests

- **Pest:**
    - `Schedule/SchedulePageTest`: client scoping (a URL `client_id` is ignored), staff (sales, owner, admin) see all, the range filter (every status, `to` excluded, the default six weeks), the 62-day limit, upcoming (the next three open visits from an hour ago, soonest first; empty) and the resource shape for a booking and an inquiry visit.
    - `Inquiry/InquirySchedulesTest`: a sent inquiry shows as a card in the client's Regray Team chat and as a visit on the Schedule page (client and staff, in `schedules` and `upcoming`); one schedule per project on send (client, `inquiry_id`, `pending`, Manila arrival, `schedule_id`), and cancelling cancels open schedules.
    - Updated for the new columns and rules: `CreateInquiryActionTest` (a failed inquiry also leaves no schedule), `CreateInquiryEndToEndTest`, `StoreInquiryRequestTest` (a date required for every project, no time needed outside a visit), `QuoteDetailsTest` ("Target deadline" read from the schedule; a time only for a visit), `InquiryStatusCardTest` (the card sends no project name), `EloquentInquiryRepositoryTest`, `InquiryServiceTest`.
- `Home/CollectionFilterTest`: the stone filter backfill test now checks `Stone::attachPrimaryFilterOptions()`, which fills the same tables, since the backfill migration is gone.
- **node:test:** `tests/js/schedule.test.mjs`.
- **Playwright (not run):** the inquiry helpers fill a date for material projects (and the time only when the field is shown). The card specs in `message/d-cards.spec.ts` expect the reference as the heading and "N projects" under it.
- **Checks run:** `php artisan test` (828 tests), PHPStan, Pint, `npm run types:check`, `npm run check`, `npm test` (153 tests), and `migrate:fresh --seed` on the test database.

# Message page: revision 2

Branch `feat/messaging`. The Message page now runs on real data and updates live through Reverb.

## What changed

### Data (new migrations only)

These migrations were later folded into the create migrations (see the top section).

- `2026_10_20_000001_add_support_and_assignment_to_conversations`:
    - Adds the `support` conversation type, with `support_client_id` (unique: one Regray Team thread per client), `assigned_to` and `assigned_at`.
    - Check constraints tie them together.
    - A trigger clears `assigned_at` when deleting the assignee's account nulls `assigned_to`.
- `2026_10_20_000002_add_role_and_delivery_to_conversation_members`: adds a member `role` (`member`, `admin`, `viewer`) and `last_delivered_message_id`.
- `2026_10_20_000003_add_replies_keys_and_system_events_to_messages`:
    - Adds `reply_to_id`, `client_key` (unique per conversation, which dedupes retried sends) and the `system` message type with `system_event` and `system_user_id`.
    - Adds a partial unique index so that one inquiry status change posts one card.
- Composite-key `conversation_members` rows are written only through the query builder in `EloquentConversationMemberRepository`, never with `->save()`. The read and delivered pointers only move forward.

### Backend

- **Enums:**
    - `ConversationType::Support`, `MessageType::System`, `ConversationMemberRole` and `SystemEvent`.
    - The permissions `conversations.create` and `support.claim`, granted to Marketing (and listed on Admin).
- **Layering:** FormRequests → `app/Actions/Message` → `app/Services/Message` → repository interfaces → Eloquent repositories. The repositories are bound in `AppServiceProvider`.
- **`ConversationPolicy`** decides `view`, `send`, `claim`, `release` and `reassign`. The routes (`can:` middleware) and the broadcast channels both use it.
- **Endpoints** (all under `messages.view`):
    - `GET /message` (Inertia, with infinite scroll).
    - `GET /message/{id}/messages` (cursor paging with `before`/`after`, `hasMore`, pointers and the staff-only `assignment`).
    - Send (throttled to 60 a minute) and edit/remove.
    - Read and delivered pointers.
    - Claim, release and reassign.
    - People search and group creation (`conversations.create`).
- **Regray Team:**
    - Registration creates the client's thread (`CreateNewUser`). It is also created on first list load.
    - The thread is visible to the client, the assignee and, while unassigned, the whole pool.
    - Claims take a row lock and run a conditional update, so the second claim gets 409 with the winner's name.
    - Release and reassign write `system` rows.
- **Inquiry status cards:**
    - `InquiryStatusService::transition()` writes the status and history and dispatches `InquiryStatusChanged` after commit.
    - Sending an inquiry dispatches it too (from nothing to `pending`), so every inquiry posts a "New inquiry" card.
    - `PostInquiryStatusCard` (queued) posts one card per status: into the inquiry's group chat when it has one, otherwise into the client's Regray Team thread (created if missing). Until groups are bound (TODO(inquiry-binding)), every card goes to the Regray Team thread.
- **Realtime (Reverb):**
    - The private channels are `user.{id}`, `support.pool` and `conversation.{id}`.
    - `MessageCreated`, `MessageUpdated`, `PointerMoved`, `ConversationActivity`, `SupportThreadClaimed`, `SupportThreadReleased` and `GroupCreated`.
    - Typing uses client whispers.
    - The client's Reverb config is shared at runtime as the Inertia prop `realtime`, because Docker images bake in `.env.example`'s `VITE_*` values.
    - `docker-compose.yml` has a `reverb` service, and `docker/Caddyfile` proxies `/app/*` to it.
- **Privacy:**
    - Clients never get the `assignment` payload, the support client block, pool viewers' receipts or other clients' visit cards.
    - While a Regray Team thread is unassigned, staff senders are shown to the client as "Regray Team".
    - `ScheduleCardResource` names staff only on staff bookings, and sends the cancel reason only on cancelled cards.

### Frontend

- `pages/message.tsx` was rewritten on `components/conversations/*`:
    - The list, rows, thread, header, bubbles, composer, assignment card, typing indicator, receipts, date separators and the inquiry, schedule and system cards.
    - The new-group and reassign dialogs.
- `hooks/use-conversation-thread.ts` handles paging, optimistic sends with `clientKey`, live events and catch-up after a reconnect.
- The logic lives in pure libs, tested under `tests/js`:
    - `conversation-skeleton`, `thread-scroll`, `long-press`, `message-grouping`, `receipts`, `assignment` and `thread-cards`.
- The old mock data and components were removed (`data/conversations.ts`, `chat-panel`, `conversation-list`, …).
- Other pages:
    - "Message us" (inquiry result) opens the Regray Team thread.
    - "Message the team" (inquiry details) shows only when the inquiry has a group.

### Seeders

- `ConversationSeeder`:
    - Gives every client a Regray Team thread.
    - Leaves `client@example.com` with two unassigned messages, and gives a second client a thread claimed by `marketing@example.com` (with its system row).
    - Adds the admin to the sample groups so the admin sees them.
- `RolePermissionSeeder` grants Marketing the two new permissions, and lists them on Admin too, as the matrix lists every permission there.

### Dependencies

- Added `laravel/reverb ^1.12`, `laravel-echo` and `pusher-js`.
- Reverb needs Guzzle 7, so `guzzlehttp/guzzle`, `promises` and `psr7` were downgraded from 8 to 7 (`composer require -W --minimal-changes`). No other package moved.

### Follow-up: reminders, limits and attachments

- **Unread reminders (email):** `messages:send-reminders` runs every five minutes on the scheduler. A message waiting unread past `messaging.reminders.after_minutes` (`MESSAGE_REMINDER_AFTER_MINUTES`, default 30) sends an email. Who gets it:
    - **A member** (a client, the staff member handling a thread, a group member) gets `UnreadMessagesNotification`, about messages someone else sent them.
    - **The support pool** (everyone with `support.claim`) gets `ClientsWaitingNotification`, about clients waiting in Regray Team threads nobody has claimed.

    The rules:
    - Only people's text messages count. Cards, system lines, the reader's own messages and removed ones do not.
    - Each message is emailed about once. `conversation_members.last_notified_message_id` and `conversations.pool_notified_message_id` (new migration) record how far the emails have got.
    - The emails name the conversation and count the messages, never quote them.
    - Only verified addresses get them.
    - They are queued. The scheduler (`php artisan schedule:work` locally) and a queue worker must both run.

- **Rate limits** (per user, per minute):
    - send: 60;
    - read/delivered receipts: 120 (`message-receipts`);
    - claim, release and reassign: 10 (`message-assignment`);
    - people search: 60 (`message-people`);
    - opening an attachment: 300 (`message-attachments`).
- **Attachments:** the paperclip sends photos (JPG, PNG, WebP), PDFs and Word documents (DOCX), up to 5 files, 10 MB each and 19 MB together (`messaging.attachments`, mirrored in `lib/message-attachments.ts`). A message may be files alone; its body is then empty, and the list reads "Sent a photo" or "Sent 3 files".
    - **Storage:** `s3-private` (the private bucket) when `APP_ENV=production`, Laravel's own storage (`storage/app/private`) otherwise. This does not follow `FILESYSTEM_PRIVATE_DISK`, so a production server missing that line still cannot keep files inside a container the next deploy replaces. `MESSAGE_ATTACHMENT_DISK` overrides it. They are stored as `messages/{conversation id}/{uuid}.{ext}`, and the sender's file name is kept only in the row.
    - **Serving:** `GET /message/attachments/{id}` is the only way to a file. It asks `ConversationPolicy::view`, the same as the thread. On S3 it redirects to a presigned link that expires after 5 minutes; locally it streams the file with `nosniff`.
    - **Removed messages:** a removed message's files stay on the disk, like its row, but are no longer listed or served (404).

## Decisions (revision 2)

- **Pool:** the Regray Team pool is everyone with `support.claim`, which is Marketing. Sales and Owner are not in it. Adding them is a one-line change to the matrix in `RolePermissionSeeder`.
- **Release:** the assignee and the admin can release a thread. Only the admin can reassign one.
- **What the client sees:** the client sees the assignee's name ("Handled by …") and the claim and release system messages.
- Staff see a Regray Team thread under the client's name, with the client's initials as its picture; the client sees it as "Regray Team" with the Regray logo. Staff cannot write in it until they claim it (409 "Assign this conversation to yourself first.").
- **Retention:** a removed message is soft-deleted and kept for good, shown as "Message removed". Nothing prunes these rows (see `Message`).
- The admin passes every policy ability through `Gate::before`, so the admin lists every Regray Team thread and can claim, release and reassign any of them. The admin still gets 409 when sending on a thread someone else handles.
- When a claim takes a thread, the rest of the pool loses it right away: reading it gets 403 and the thread leaves their lists live, over `support.pool`. A pool member who still tries to send gets 409 "{name} is handling this conversation." (changed after the E2E run; see `tests/e2e/message/REPORT.md`, "Fixes").

## TODO list

- **`TODO(inquiry-binding)`:** groups are not bound to inquiries yet.
    - Until they are, no real inquiry has a group, so status cards are not posted for real data and "Message the team" stays hidden.
    - Marked in `ConversationPolicy`, `PostInquiryStatusCard`, `InquiryStatusService`, `InquiryDetailsService`, `InquiryDetailsResource`, `GroupConversationService`, `ConversationSeeder` and `pages/inquiry-details.tsx`.
- **`TODO(booking)`:** post a `schedule_card` when a client books and on every later status change. It goes where inquiry cards go: the inquiry's group chat when there is one, otherwise the client's Regray Team thread. The card's rendering is already built. Marked in `Schedule`, `PostInquiryStatusCard` and `pages/schedule-book.tsx`.
- **`TODO(staff-inquiry-scope)`:** the inquiry details page only finds the viewer's own inquiries (`InquiryDetailsService::forClient`), so a staff member who clicks an inquiry card gets a 404. Marked in `components/conversations/inquiry-card.tsx`.

## Tests

- **Pest, `tests/Feature/Message`:**
    - `ConversationPolicyTest`: the full role matrix.
    - `SupportThreadTest`: registration, idempotent creation, claim and the 409 for a second claim, visibility after a claim, the send 409s, release, reassign, `assignment` privacy, name masking, and the FK trigger.
    - `ThreadMessagesTest`: paging, `client_key` dedupe, edit and remove, and pointers.
    - `GroupConversationTest`.
    - `InquiryStatusCardTest`.
    - `BroadcastChannelsTest`.
    - `ScheduleCardTest`.
- **node:test, `tests/js`:** one file per pure lib listed above.
- **Checks run:** Pint, PHPStan, `php artisan test` (761 tests), `npm run check`, `npm run types:check`, `npm run test` (134 tests) and `migrate:fresh --seed`.

## Not verified

- **Truly simultaneous claims:** the race test sends its two claims one after the other. One winner is guaranteed by the `SELECT … FOR UPDATE` lock plus the `UPDATE … WHERE assigned_to IS NULL`, not by a parallel test.
- **"A non-assignee gets 409":** now tested with both the admin and another pool member (`SupportThreadTest`).
- **Docker:** the `reverb` service and the Caddy `/app/*` proxy were not run under Docker. Realtime was tested locally with `php artisan reverb:start`.
- **Manual browser checks:** claim, the live list update, "Handled by", typing, live delivery, the name label and "Seen" were all checked with two browsers (client and marketing). Not yet checked by hand:
    - release, reassign, edit, remove and reply in the browser (covered by the HTTP tests);
    - long-press on a phone emulator (the reducer is unit tested);
    - reconnect catch-up after restarting Reverb;
    - card sizes at 320, 390 and 1280 px.

    The Playwright end-to-end suite that follows covers these.

## Out-of-scope observations

- `composer test` takes longer than Composer's default 300 s process timeout on this machine, so it stops partway. `php artisan test` runs the suite without that limit. Not changed.
