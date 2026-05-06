# News Scraper & AI Summarizer — Project Documentation

## Table of Contents
1. [Project Overview](#project-overview)
2. [Architecture](#architecture)
3. [Tech Stack](#tech-stack)
4. [Database Schema](#database-schema)
5. [API Endpoints](#api-endpoints)
6. [Core Workflows](#core-workflows)
7. [Admin Features](#admin-features)
8. [User Features](#user-features)
9. [Implementation Phases](#implementation-phases)
10. [Deployment & Scaling](#deployment--scaling)

---

## Project Overview

**Goal:** Build a news aggregator platform that:
- Scrapes news from multiple configurable sources
- Automatically summarizes articles using Claude AI
- Translates summaries to multiple languages (ID + EN)
- Classifies articles into categories
- Extracts keywords for searchability
- Provides flexible UI for reading in preferred language
- Allows authenticated users to manually request translations

**Core Insight:** One article is processed once with default languages (ID + EN). Users can request additional translations on-demand without re-scraping or re-summarizing the original content.

---

## Architecture

### System Flow

```
┌─────────────────────────────────────────────────────────┐
│                    ADMIN DASHBOARD                      │
│  • Manage news sources (add/verify/edit/deactivate)     │
│  • Set scraping schedules (global or per-source)        │
│  • Manage article categories (master data)              │
│  • Monitor scraping & processing logs                   │
│  • Manual trigger translations                          │
└──────────────────────┬──────────────────────────────────┘
                       │
         ┌─────────────┴─────────────┐
         │                           │
         ▼                           ▼
  ┌──────────────┐            ┌──────────────┐
  │  SCHEDULER   │            │ VERIFICATION │
  │  (Laravel    │            │  (Verify if  │
  │   Queue)     │            │   source can │
  │              │            │   be scraped)│
  └──────┬───────┘            └──────────────┘
         │
         ▼
  ┌──────────────────────┐
  │  SCRAPER             │
  │  • Fetch HTML        │
  │  • Parse selectors   │
  │  • Extract content   │
  │  • Download image    │
  │  • Check duplicates  │
  └──────┬───────────────┘
         │
         ▼
  ┌──────────────────────┐
  │  ARTICLE STORED      │
  │  (raw, unprocessed)  │
  └──────┬───────────────┘
         │
         ▼
  ┌──────────────────────────────┐
  │  AI PROCESSING (Claude API)  │
  │  • Input: article body       │
  │  • Summarize: ID + EN        │
  │  • Extract keywords          │
  │  • Classify category         │
  │  • Return JSON               │
  └──────┬───────────────────────┘
         │
         ▼
  ┌──────────────────────────────┐
  │  STORE RESULTS               │
  │  • article_translations      │
  │  • keywords                  │
  │  • category                  │
  └──────┬───────────────────────┘
         │
         ▼
  ┌──────────────────────────────┐
  │  API ENDPOINTS               │
  │  • List articles (default    │
  │    language: ID)             │
  │  • Get single article        │
  │  • Request translation       │
  └──────┬───────────────────────┘
         │
    ┌────┴──────┐
    │            │
    ▼            ▼
┌────────────┐ ┌────────────┐
│  WEB       │ │  MOBILE    │
│  (Nuxt)    │ │  (Flutter) │
└────────────┘ └────────────┘
```

### Component Breakdown

| Component           | Purpose                                  | Tech                           |
| ------------------- | ---------------------------------------- | ------------------------------ |
| **Admin Dashboard** | Configure sources, schedules, categories | Nuxt.js / Laravel Filament     |
| **Scheduler**       | Trigger scraping at defined intervals    | Laravel Task Scheduler + Queue |
| **Scraper**         | Extract content from HTML                | Goutte / cURL                  |
| **AI Processor**    | Summarize, translate, classify           | Claude API                     |
| **Database**        | Store articles, translations, metadata   | MySQL 8.0+                     |
| **API**             | Serve data to frontends                  | Laravel REST API               |
| **Web Frontend**    | Desktop news feed                        | Nuxt.js 3                      |
| **Mobile Frontend** | Mobile news app                          | Flutter                        |

---

## Tech Stack

### Backend
- **Framework:** Laravel 11
- **Database:** MySQL 8.0+
- **Queue:** Redis (production) / Database (MVP)
- **HTML Parser:** Goutte (Symfony DomCrawler)
- **API:** Claude (Anthropic SDK)
- **Auth:** Laravel Sanctum (API tokens)

### Frontend (Web)
- **Framework:** Nuxt.js 3
- **UI:** Tailwind CSS
- **State Management:** Pinia
- **HTTP:** Fetch / Axios

### Frontend (Mobile)
- **Framework:** Flutter 3.x
- **State:** Provider / Riverpod
- **HTTP:** Dio / http package

### DevOps
- **Hosting:** Railway / Render (Laravel) + Vercel (Nuxt)
- **Monitoring:** Sentry (error tracking)
- **Logging:** Laravel Logs
- **CI/CD:** GitHub Actions

---

## Database Schema

### Entity Relationship

```
sources (1) ──────────── (N) articles
            └──────────────── scrape_tasks

articles (1) ──────────── (N) article_translations
articles (1) ──────────── (N) translation_requests

categories ─────── (for classification, lookup)
users (1) ──────── (N) translation_requests
users (1) ──────── (N) user_saved_articles
articles (1) ────────────── (N) user_saved_articles
```

### Tables Detail

#### `sources`
Configuration untuk setiap news source

```
id (UUID)
name (VARCHAR 255)           — "Detik", "Kompas", "CNN"
base_url (VARCHAR 255)       — "https://detik.com"
description (TEXT)           — Optional: source deskripsi
selector_title (VARCHAR 255) — CSS selector untuk scrape judul
selector_body (VARCHAR 255)  — CSS selector untuk scrape artikel
selector_image (VARCHAR 255) — CSS selector untuk scrape gambar
schedule_type (ENUM)         — 'interval', 'cron', 'once'
schedule_value (VARCHAR 255) — '5 minutes', '0 0 * * *', etc
last_scraped_at (TIMESTAMP)  — Last successful scrape
next_scrape_at (TIMESTAMP)   — Next scheduled scrape
is_active (BOOLEAN)          — Active/Inactive toggle
created_at (TIMESTAMP)
updated_at (TIMESTAMP)
```

#### `articles`
Raw artikel yang sudah discrape

```
id (UUID)
source_id (UUID, FK)         — Which source scraped this
title (TEXT)                 — Article title
body (LONGTEXT)              — Full article content
original_url (VARCHAR 255)   — Full URL ke source
image_url (VARCHAR 255)      — Thumbnail/featured image URL
hash_content (VARCHAR 255)   — SHA256(title + body) untuk dedup
scraped_at (TIMESTAMP)       — When scraped
created_at (TIMESTAMP)

INDEX: idx_source_id
INDEX: idx_hash_content (UNIQUE untuk dedup)
```

#### `article_translations`
Processed & translated versions

```
id (UUID)
article_id (UUID, FK)        — Which article
language (VARCHAR 10)        — 'id', 'en'
summary (LONGTEXT)           — AI-generated summary
keywords (JSON)              — ["keyword1", "keyword2", ...]
category (VARCHAR 50)        — tech, finance, sports, politics, health, entertainment, other
processed_at (TIMESTAMP)     — When AI processed this
created_at (TIMESTAMP)

UNIQUE: (article_id, language) — Prevent duplicate translations
INDEX: idx_article_id
INDEX: idx_language
```

#### `categories`
Master data untuk kategori

```
id (UUID)
name (VARCHAR 100)           — 'technology', 'finance', 'sports'
slug (VARCHAR 100)           — 'tech', 'finance', 'sports'
icon (VARCHAR 50)            — Optional emoji atau icon class
color (VARCHAR 10)           — Optional: UI color hex
is_active (BOOLEAN)
created_at (TIMESTAMP)

UNIQUE: slug
```

#### `translation_requests`
Track manual translation requests dari users

```
id (UUID)
article_id (UUID, FK)
user_id (UUID, FK)           — Who requested
target_language (VARCHAR 10) — 'jv', 'su', etc (future expansion)
status (ENUM)                — 'pending', 'processing', 'completed', 'failed'
error_message (TEXT)         — If failed
requested_at (TIMESTAMP)
completed_at (TIMESTAMP)
created_at (TIMESTAMP)

INDEX: idx_article_id
INDEX: idx_status
```

#### `user_saved_articles`
User bookmarks/saved articles

```
id (UUID)
user_id (UUID, FK)
article_id (UUID, FK)
saved_at (TIMESTAMP)

UNIQUE: (user_id, article_id)
```

---

## API Endpoints

### Authentication
```
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/logout
POST   /api/auth/refresh
```

### Public Endpoints (User)

#### Articles Feed
```
GET /api/articles
  Query params:
    - lang=id|en (default: id)
    - category=tech|finance|sports|... (optional)
    - search=keyword (optional)
    - page=1 (default)
    - per_page=20 (default)
  
  Response:
  {
    data: [
      {
        id: UUID,
        title: string,
        image_url: string,
        source: { id, name },
        summary: string,        ← dari article_translations[language=id]
        keywords: [string],
        category: string,
        created_at: datetime,
        is_saved: boolean       ← current user saved this?
      }
    ],
    pagination: { total, page, per_page }
  }
```

#### Single Article with All Translations
```
GET /api/articles/:id
  
  Response:
  {
    id: UUID,
    title: string,
    body: string,
    image_url: string,
    source: { id, name, base_url },
    original_url: string,
    translations: {
      id: {
        summary: string,
        keywords: [string],
        category: string,
        processed_at: datetime
      },
      en: {
        summary: string,
        keywords: [string],
        category: string,
        processed_at: datetime
      }
    },
    is_saved: boolean,
    created_at: datetime
  }
```

#### Get Specific Translation
```
GET /api/articles/:id/translation/:language
  
  Response:
  {
    language: string,
    summary: string,
    keywords: [string],
    category: string,
    processed_at: datetime
  }
```

#### Request Manual Translation
```
POST /api/articles/:id/translate
  Body:
  {
    target_language: "jv"  ← for future expansion
  }
  
  Response:
  {
    status: "pending|processing|completed",
    message: string
  }
```

#### Categories List
```
GET /api/categories
  
  Response:
  [
    {
      id: UUID,
      name: string,
      slug: string,
      color: string
    }
  ]
```

#### Saved Articles
```
GET /api/user/saved
  Query: lang=id, page=1, per_page=20
  
  Response: Same as GET /api/articles
```

#### Toggle Save Article
```
POST /api/articles/:id/save
  
  Response:
  {
    saved: boolean
  }
```

### Admin Endpoints (Protected)

#### Source Management
```
GET    /api/admin/sources
POST   /api/admin/sources
PUT    /api/admin/sources/:id
DELETE /api/admin/sources/:id
POST   /api/admin/sources/:id/verify         ← Test scrape
POST   /api/admin/sources/:id/scrape-now     ← Trigger immediately
```

#### Schedule Management
```
GET    /api/admin/schedules
PUT    /api/admin/schedules/:sourceId        ← Update schedule
POST   /api/admin/schedules/:sourceId/run    ← Run now
```

#### Category Management
```
GET    /api/admin/categories
POST   /api/admin/categories
PUT    /api/admin/categories/:id
DELETE /api/admin/categories/:id
```

#### Dashboard & Logs
```
GET /api/admin/stats
  Response:
  {
    total_articles: number,
    today_scraped: number,
    total_sources: number,
    active_sources: number,
    processing_queue: number,
    last_error: string
  }

GET /api/admin/logs?type=scrape|process|error&limit=50
  Response:
  [
    {
      timestamp: datetime,
      type: string,
      message: string,
      source_id?: UUID,
      article_id?: UUID
    }
  ]
```

---

## Core Workflows

### Workflow 1: Auto Scrape & Process

**Trigger:** Scheduler (cron / recurring task)

**Steps:**

1. **Check Schedule**
   - Find all ACTIVE sources
   - Check which ones should run now (based on schedule_type + schedule_value)

2. **Scrape Source**
   - Fetch HTML from source URL
   - Parse using CSS selectors (title, body, image)
   - Extract article metadata

3. **Deduplication Check**
   - Hash article content: SHA256(title + body)
   - Check if hash exists in articles table
   - If exists: skip (duplicate)
   - If new: continue

4. **Create Article Record**
   - Insert into articles table
   - Store: title, body, original_url, image_url, hash_content

5. **Queue AI Processing**
   - Create job: ProcessArticleJob(article_id)
   - Dispatch to queue (Redis / Database)

6. **Process with Claude**
   - Fetch article from DB
   - Build prompt: "Summarize artikel berikut dalam Bahasa Indonesia dan English. Output dalam format JSON..."
   - Call Claude API dengan max_tokens=500, temperature=0.7
   - Parse response JSON

7. **Expected Claude Response:**
   ```json
   {
     "summary_id": "Ringkasan dalam Bahasa Indonesia...",
     "summary_en": "Summary in English...",
     "keywords": ["keyword1", "keyword2", "keyword3"],
     "category": "tech"
   }
   ```

8. **Store Translation Results**
   - Insert 2 records ke article_translations:
     - (article_id, language='id', summary_id, keywords, category)
     - (article_id, language='en', summary_en, keywords, category)

9. **Update Article Status**
   - Update article: processed_at = now()

10. **Log Success**
    - Add to logs: "Article {id} scraped and processed from {source}"

**Error Handling:**
- Scrape failed → Log error, skip article
- Claude API failed → Retry with exponential backoff (max 3x)
- If final failure → Store article but mark as unprocessed

---

### Workflow 2: User Requests Manual Translation

**Trigger:** User clicks "Translate to [Language]" button

**Prerequisites:**
- Article exists
- Translation doesn't already exist for that language
- User authenticated

**Steps:**

1. **Check Cache**
   - Query article_translations WHERE article_id=? AND language=?
   - If exists: return existing translation (don't re-process)

2. **Verify Article**
   - Fetch article from DB
   - If not found: return 404

3. **Create Request Record**
   - Insert to translation_requests: status='pending', target_language
   - Return immediately to user (async processing)

4. **Queue Translation Job**
   - ProcessTranslationJob(article_id, target_language, user_id)
   - Dispatch to queue

5. **Call Claude API**
   - Prompt: "Translate artikel berikut ke bahasa {target_language}. Output format: JSON dengan keys 'summary' dan 'keywords'"
   - Same parameters as auto-process

6. **Store Result**
   - Insert to article_translations: language=?, summary, keywords
   - Update translation_requests: status='completed'

7. **Notify User** (optional)
   - Emit WebSocket event / Push notification
   - Frontend auto-refresh

**Error Handling:**
- Similar to auto-process
- Update translation_requests.status='failed' + error_message

---

### Workflow 3: Admin Verify Source

**Trigger:** Admin clicks "Verify" button on add/edit source

**Steps:**

1. **Fetch Source Configuration**
   - Get: base_url, selector_title, selector_body, selector_image

2. **Test Fetch**
   - Make HTTP GET request to base_url
   - If failed (timeout, 404, etc): return error "Cannot reach source"

3. **Test Parse**
   - Parse HTML dengan selectors
   - Extract sample (1-3 items)
   - If empty/null: return error "Selectors don't match content"

4. **Return Sample**
   - Show admin: title, body preview, image
   - Let admin confirm selectors are correct

5. **Save Source**
   - If confirmed: insert/update sources table
   - Set is_active=true

---

### Workflow 4: Admin Manage Categories

**Trigger:** Admin visits Category Management

**Actions:**
- Add category: insert to categories
- Edit category: update name, slug, color
- Delete category: soft-delete or cascade (depends on policy)
- Toggle active: update is_active flag

Categories are used for:
- Claude classification (choose from master list)
- Frontend filtering UI
- Analytics/reporting

---

## Admin Features

### Source Management Dashboard

**Create/Edit Source:**
- Input fields:
  - Name
  - Base URL
  - CSS selectors (title, body, image)
  - Description
  - Status toggle (active/inactive)

- Actions:
  - Save
  - Verify (test scrape)
  - Delete
  - View last scraped articles

**Source List View:**
- Table with columns: Name, Base URL, Status, Last Scraped, Next Scrape, Actions
- Filter: Active/Inactive
- Sort: Name, Last Scraped, etc
- Bulk actions: Enable/Disable multiple

---

### Schedule Configuration

**Global Schedule** (affects all sources):
- Type: Interval (minutes), Cron expression, Manual only
- Example: "Every 5 minutes", "Daily at 9 AM", "Manual trigger only"

**Per-Source Schedule** (override global):
- Each source can have custom schedule
- Example: "Kompas: Every 1 hour", "BBC: Every 30 minutes", "Reddit: Every 5 minutes"

**UI Elements:**
- Dropdown: Interval / Cron / Manual
- If Interval: number input (minutes)
- If Cron: text input (0 0 * * *)
- If Manual: just a "Run Now" button
- Display: "Next scheduled run: 2024-05-06 14:30"
- Button: "Run Now" (force immediate execution)

---

### Category Management

**CRUD Operations:**
- Add new category
- Edit name, slug, color, icon
- Delete category
- Mark as active/inactive

**Default Categories:**
- Technology
- Finance
- Sports
- Politics
- Health
- Entertainment
- Other

**UI for Claude:**
- When Claude processes: categories list is passed as enum
- Example: "Classify article into one of: {tech, finance, sports, ...}"

---

### Monitoring & Logs

**Dashboard Stats:**
- Total articles scraped
- Articles scraped today
- Active sources count
- Processing queue length (pending jobs)
- Last error (if any)

**Logs Viewer:**
- Filters: Type (scrape, process, error), Date range, Source
- Display: Timestamp, Type, Message, Details
- Search capability
- Auto-refresh (optional)

---

## User Features

### News Feed

**Main Display:**
- Articles list (infinite scroll or pagination)
- Show: Title, Thumbnail, Summary (default language: ID), Source, Published date
- Button: "Read More" (expand modal or go to source)
- Button: "Translate" (show available translations or request new)
- Button: "Save" (bookmark)
- Button: "Share" (copy link, native share dialog)

**Filters:**
- By Category (dropdown/chips)
- By Source (optional)
- Search keyword (in title + summary + keywords)

**Sorting:**
- Newest first (default)
- Most commented (if comments feature added)
- Trending (if analytics added)

---

### Article Detail

**Modal / Full Page:**
- Article title
- Featured image
- Source info (name, link to original)
- Summary in default language (ID)
- Tab/Button: View other languages
  - If translation exists: show it
  - If not: "Request Translation" button
- Keywords (clickable → filter by keyword)
- Category badge
- "Read Full Article" link (external)
- Save / Share buttons

---

### Multi-Language View

**Language Selector:**
- Tabs or buttons: ID | EN | ...
- Show which languages available
- For missing language: "Request translation" button

**Translation Request:**
- User clicks button
- UI shows: "Requesting translation to {language}..."
- Backend processes async
- When done: UI updates automatically or shows notification
- User can then read translation

---

### Saved Articles

**Bookmark Feature:**
- Heart icon on articles to save
- Dedicated page: "My Saved Articles"
- Sort: Recently saved, Date added
- Filter: By category, language
- Delete: Remove from saved
- Export: (optional) download as PDF/CSV

---

### Search

**Functionality:**
- Search in: title, summary, keywords
- Real-time suggestions (as user types)
- Filters:
  - Date range
  - Category
  - Source
  - Language (which translation to search in)

---

## Implementation Phases

### Phase 1: MVP (Weeks 1-2)

**Backend:**
- [ ] Database setup (MySQL)
- [ ] Laravel project + basic auth
- [ ] Sources table + CRUD endpoints
- [ ] Basic scraper job (single source test)
- [ ] Article model + storage
- [ ] Claude API integration (test)
- [ ] Process articles job
- [ ] Category master data

**Frontend (Web):**
- [ ] Nuxt project setup
- [ ] Login page
- [ ] Articles list (simple)
- [ ] Single article view
- [ ] Basic styling (Tailwind)

**Frontend (Mobile):**
- [ ] Flutter project setup
- [ ] Login screen
- [ ] Articles feed
- [ ] Article detail screen

**Testing:**
- Manual test: Scrape 1 source → Process with Claude → Display in UI

---

### Phase 2: Core Features (Weeks 3-4)

**Backend:**
- [ ] Scheduler setup (Laravel Task)
- [ ] Deduplication (hash-based)
- [ ] Multi-source scraping
- [ ] Translation table + storage
- [ ] Manual translation endpoint
- [ ] Translation request tracking

**Frontend:**
- [ ] Language selector / tabs
- [ ] Manual translation trigger
- [ ] Category filters
- [ ] Search functionality
- [ ] Save/bookmark feature

**Admin:**
- [ ] Admin dashboard (basic)
- [ ] Source management UI
- [ ] Schedule configuration
- [ ] Category management

---

### Phase 3: Polish & Optimization (Week 5)

**Backend:**
- [ ] Error handling & retry logic
- [ ] Logging system
- [ ] Performance optimization
- [ ] Rate limiting (Claude API)

**Frontend:**
- [ ] UI/UX refinements
- [ ] Mobile responsiveness
- [ ] Error states
- [ ] Loading states

**Admin:**
- [ ] Dashboard stats
- [ ] Logs viewer
- [ ] Source verification

---

### Phase 4: Deployment & Monitoring (Week 6+)

- [ ] Database migrations setup
- [ ] Environment configuration
- [ ] CI/CD pipeline
- [ ] Deploy to production
- [ ] Monitor errors (Sentry)
- [ ] Monitor performance
- [ ] User feedback loop

---

## Deployment & Scaling

### Hosting Strategy

**Backend (Laravel + MySQL):**
- Option A: Railway (easy, auto-deploy from Git)
- Option B: Vercel Functions + Supabase
- Option C: Self-hosted (VPS + Docker)

**Frontend (Nuxt):**
- Vercel (native support for Nuxt)
- Netlify (alternative)

**Mobile (Flutter):**
- Android: Google Play Store
- iOS: Apple App Store
- Or: PWA version (web-based)

---

### Scaling Considerations

**Database:**
- Index on: source_id, hash_content, language, category
- Partition article_translations by language (if huge volume)

**Jobs/Queue:**
- Use Redis for production (instead of database queue)
- Scale workers: horizontal pod autoscaling (if Kubernetes)

**API Caching:**
- Cache articles list (5-10 min TTL)
- Cache categories (daily)

**Rate Limiting:**
- Claude API: Monitor token usage, implement cost tracking
- User API: Rate limit by IP/user

**CDN:**
- Image URLs: Use CloudFlare / AWS CloudFront
- Static assets: Vercel CDN

---

## Notes & Considerations

### Future Enhancements

1. **Multi-region scraping** (different selectors for different versions of same news site)
2. **Comment system** (allow users to discuss articles)
3. **Recommendation engine** (based on read history)
4. **Mobile app push notifications** (new articles in favorite categories)
5. **Email digest** (daily summary of top articles)
6. **Audio reading** (TTS for article summaries)
7. **Advanced analytics** (trending topics, sentiment analysis)
8. **Subscription/Premium features** (early access, ad-free)

### Cost Estimation (Rough)

- **Claude API:** ~$0.01-0.05 per article (summarize + translate)
  - 100 articles/day = $1-5/day = $30-150/month
- **Hosting:** $10-20/month (Railway / Vercel hobby)
- **Database:** $10-20/month (managed MySQL)
- **Image hosting:** Free (just store URLs)

### Potential Challenges

1. **Scraper selector breakage** — Source updates HTML → selectors break
   - Solution: Admin gets notified, manual re-verify
2. **Claude API rate limits** — Too many articles
   - Solution: Queue batching, prioritization
3. **Duplicate detection edge cases** — Same news reported differently
   - Solution: Fuzzy matching on title + body similarity
4. **Content moderation** — Offensive/spam articles
   - Solution: Add Claude classification (safe/unsafe), manual review queue
5. **Language expansion** — Adding new languages
   - Solution: Just add to Claude prompt + database, no code changes

---

## Contact & Support

- **Developer:** Nandang (Nannnde)
- **Stack:** Laravel + Nuxt + Flutter
- **GitHub:** (link to repo)
- **Status:** MVP In Development

---

*Last Updated: May 2024*
*Version: 0.1 (MVP Planning)*
