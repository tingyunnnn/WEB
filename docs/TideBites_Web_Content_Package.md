# TideBites — Website Content Package (Phase I)

> **Status:** Implementation draft v1 — implement immediately; the team can review and revise later (2026-11-05)  
> **Course:** Web Programming — Team Project, Fall 2026, Phase I  
> **Deadline:** 2026-11-12 (Thu), 17:00  
> **Repository:** https://github.com/tingyunnnn/WEB  
> **Content owner:** Team member responsible for all website copy, food images, and menu content  
> **Scope:** Content specification + implementation instructions + repository reorganization. This document does not itself modify the GitHub repository.

## 0. Team decisions / important notes (read first)

1. **Brand name in this draft: `TideBites`.** Use it as the current implementation default without waiting for team confirmation. If the team changes the name later, update every occurrence consistently (page title, sidebar, header, about text, footer, alt text, slides).
2. **Use exactly 10 menu items**, each shown as its own food card/blog post with **one original food photograph, the food name as its title, and an English description**. Show **no more than 6 cards on any one page**. The suggested split is 6 cards on page 1 and 4 on page 2.
3. **All published on-page text must be in English.** Chinese notes below are internal team instructions only.
4. **The photos supplied by the team must be confirmed as taken by team members** (or otherwise comply with the instructor's own-photo rule). **No W3 template food photos, stock photos, online food photos, or AI-generated replacement food photos.**
5. **Some original photos contain identifiable shop branding, people, or other distractions.** The photo files in the companion ZIP are *unprocessed originals for editing*, NOT final web-ready assets. Edit the actual image before publication (especially if a click-to-enlarge view reveals the uncropped original). Do not rely only on CSS cropping to hide logos in a thumbnail.
6. **Prices, discount terms, delivery hours, and brand claims below are fictional suggestions** for the class website; they are *not verified real restaurant facts*. Use these consistently as a class-project concept for now; the team can revise them at the next discussion. Do not claim an order, payment, social link, or delivery feature works if it has not been implemented.
7. **Do not use fictional customer reviews presented as genuine testimonials.** Replace the current `Customer Reviews` area with the editorial `Student Favorites` cards supplied below.

---


## 0A. Mandatory repo refactor — do together with content implementation

**Current main branch status (checked 2026-11-05):** all of `index.html`, `style.css`, `script.js`, and seven PNG icon assets are in the repository root. The HTML references W3 demo `/w3images/...` placeholders, which are not part of this repo. Fix all content AND structure in the same PR.

### Target repository layout

```text
WEB/
├── index.html                      # Keep at root: Chrome can open it directly
├── css/
│   └── style.css
├── js/
│   └── script.js
├── assets/
│   ├── icons/
│   │   ├── cupcake.png
│   │   ├── discount.png
│   │   ├── dollar.png
│   │   ├── information-button.png
│   │   ├── lunch.png
│   │   ├── menu.png
│   │   └── soft-drink.png
│   └── images/
│       ├── thai-basil-pork-rice.jpg
│       ├── chicken-oyakodon-bowl.jpg
│       ├── taiwanese-fish-ball-noodle-soup.jpg
│       ├── roasted-chicken-set-meal.jpg
│       ├── tomato-udon-soup.jpg
│       ├── beef-and-egg-noodles.jpg
│       ├── chilled-pork-noodle-bowl.jpg
│       ├── watermelon-snow-ice.jpg
│       ├── mango-snow-ice.jpg
│       └── peanut-rice-dumplings.jpg
├── docs/
│   └── TideBites_Web_Content_Package.md
└── README.md
```

### Refactor rules

1. **Keep `index.html` in the repository root**, so opening it in Google Chrome works from the extracted ZIP with no build, localhost, or server required. Do not switch to Vite, React, Next.js, npm-based tooling, or a CMS.
2. Move root `style.css` to `css/style.css` and `script.js` to `js/script.js` (preserve and improve their behavior). Move all existing PNG icons to `assets/icons/`. If an existing icon is no longer used after cleanup, remove it only after verifying no references remain.
3. Put all 10 cleaned, publication-ready original-food photographs in `assets/images/`. Source originals arrive under `photos_to_edit/` from the companion ZIP. **Do not publish source originals containing identifiable branding.** If cleanup is not possible, report the affected file and leave it as a clearly identified incomplete task instead of falsely claiming compliance.
4. Use **relative paths** from root `index.html`: `css/style.css`, `js/script.js`, `assets/icons/menu.png`, `assets/images/mango-snow-ice.jpg`, etc. Never use leading `/` paths, local drive letters, or stale `/w3images/` W3 demo links. Check all card thumbnails and enlarged photos.
5. Put this document in `docs/` when committing. Update the README in English with purpose, folder structure, local Chrome launch instructions, content ownership / photo sourcing rules, and implementation notes.
6. Don't retain dead placeholder menu pages, fake social media links, testimonial identities, stale template photos, or old blog posts. Keep `Powered by w3.css` with its required link at the absolute bottom.
7. Use the current brand/menu text and prices as **implementation defaults**. **Don't stop to request review or approval**; team feedback will come in a later revision.
8. Make changes on a new feature branch (suggested `codex/tidebites-content-and-structure`), not by rewriting `main`; open a PR when done. Avoid clobbering teammates' newer commits; inspect current repo state first.
9. Validate screen widths 375 / 1024 / 1366 px; test filter and pagination on All, Meals, Desserts; check image enlargement and close controls; test opening `index.html` locally via `file://` in Chrome without a dev server.


## 1. Brand identity — approved copy candidate (English)

| Field | Website copy |
|---|---|
| **Brand / Website name** | **TideBites** |
| Brand descriptor | Campus Food Delivery |
| Main tagline | **Catch a wave of flavor.** |
| Hero subheading | Comfort food, cool treats, and little breaks worth looking forward to—delivered with a coastal twist. |
| Header / page heading | **TideBites** |
| Sidebar welcome line | Your next study-break favorite. |
| Menu heading (optional) | **Explore Our Menu** |
| Menu intro (optional) | From hearty bowls to icy desserts, find something to match your mood. |
| About heading | **About TideBites** |
| Student deals heading | **Student Deals** |
| Photo gallery heading (if needed) | **A Closer Look at Our Food** |

### Website browser title and meta description

- `<title>TideBites | Campus Food Delivery</title>`
- Meta description: `Discover satisfying meals and refreshing desserts at TideBites, a student-focused virtual restaurant with an ocean-inspired vibe.`

### Brand direction (internal design guidance; not copy)

- **Core identity:** playful coastal / ocean theme, not seafood-only. Keep names of food items clear and literal; put ocean vocabulary in the brand and UI microcopy.
- **Audience:** college students who want affordable, appetizing meals, convenient browsing and sweet treats.
- **Suggested palette:** Deep Ocean `#123B55` / Tide Teal `#0D7B8D` / Seafoam `#E8F5F3` / Sand `#F8F2E7` / Coral accent `#F47C6A`.
- **Design idea:** wave accents, soft rounded cards, clear food photography, warm and friendly writing. Balance light backgrounds with saturated food photos.
- **Brand mark:** Prefer a text-based TideBites wordmark or a simple original CSS wave decoration. Do **not** insert a sourced restaurant logo.

---

## 2. Final 10 menu items — complete English content

**Category labels for UI:** `ALL` / `MEALS` / `DESSERTS`  
**Currency:** TWD (`NT$`)  
**Price status:** All prices are **optional proposed display prices**, NOT actual prices from the businesses in the photos.

### Menu table (the source of truth for implementation)

| ID | Page | Category | Food title (use exactly as card heading) | Price (suggested) | Original photo | Final asset path |
|---|---:|---|---|---:|---|---|
| 01 | 1 | Meals | Thai Basil Pork Rice | NT$129 | `IMG_8621.jpg` | `assets/images/thai-basil-pork-rice.jpg` |
| 02 | 1 | Meals | Chicken Oyakodon Bowl | NT$139 | `IMG_8686.jpg` | `assets/images/chicken-oyakodon-bowl.jpg` |
| 03 | 1 | Meals | Taiwanese Fish Ball Noodle Soup | NT$139 | `IMG_1170.jpg` | `assets/images/taiwanese-fish-ball-noodle-soup.jpg` |
| 04 | 1 | Meals | Roasted Chicken Set Meal | NT$169 | `IMG_0353.jpg` | `assets/images/roasted-chicken-set-meal.jpg` |
| 05 | 1 | Meals | Tomato Udon Soup | NT$159 | `IMG_1057.jpg` | `assets/images/tomato-udon-soup.jpg` |
| 06 | 1 | Meals | Beef and Egg Noodles | NT$179 | `IMG_0817.jpg` | `assets/images/beef-and-egg-noodles.jpg` |
| 07 | 2 | Meals | Chilled Pork Noodle Bowl | NT$149 | `IMG_2141.JPG` | `assets/images/chilled-pork-noodle-bowl.jpg` |
| 08 | 2 | Desserts | Watermelon Snow Ice | NT$99 | `IMG_1348.jpg` | `assets/images/watermelon-snow-ice.jpg` |
| 09 | 2 | Desserts | Mango Snow Ice | NT$109 | `IMG_1347.jpg` | `assets/images/mango-snow-ice.jpg` |
| 10 | 2 | Desserts | Peanut Rice Dumplings | NT$79 | `IMG_8756.jpg` | `assets/images/peanut-rice-dumplings.jpg` |

### Card-by-card descriptions and accessible image text

#### 01 — Thai Basil Pork Rice
- **Category:** Meals
- **Description (website):** Savory minced pork with fragrant basil and vegetables, served over fluffy rice and topped with two sunny-side-up eggs. A hearty pick for a busy day.
- **Image alt text:** Thai basil minced pork and vegetables served with rice and two fried eggs.

#### 02 — Chicken Oyakodon Bowl
- **Category:** Meals
- **Description (website):** A comforting rice bowl with tender chicken, egg, and sweet onions, finished with a sprinkle of green onions and sesame.
- **Image alt text:** Chicken and egg rice bowl topped with green onions, sesame, and vegetables.

#### 03 — Taiwanese Fish Ball Noodle Soup
- **Category:** Meals
- **Description (website):** Springy fish balls, sliced chicken, noodles, and fresh greens in a light, savory broth. A warm bowl of comfort between classes.
- **Image alt text:** Taiwanese noodle soup with fish balls, sliced chicken, and green vegetables.

#### 04 — Roasted Chicken Set Meal
- **Category:** Meals
- **Description (website):** A golden roasted chicken leg served with steamed rice, vegetables, and tasty sides. Everything you need in one satisfying plate.
- **Image alt text:** Roasted chicken leg set with rice, vegetables, and small side dishes.

#### 05 — Tomato Udon Soup
- **Category:** Meals
- **Description (website):** Thick udon noodles in a tangy tomato broth with sliced meat, egg, and green onions. Bright, savory, and wonderfully comforting.
- **Image alt text:** Tomato broth udon with sliced meat, egg, green onions, and fish cake.
- **Verification note:** Confirm the actual meat and noodle type before adding a more specific protein name (such as beef or pork) to the title.

#### 06 — Beef and Egg Noodles
- **Category:** Meals
- **Description (website):** Juicy beef slices and a sunny-side-up egg over a bowl of noodles. A filling favorite when you need an energy boost.
- **Image alt text:** Beef slices and a fried egg served over noodles in a dark bowl.
- **Verification note:** Confirm the meat is beef; if not, update the title, description, and alt text consistently.

#### 07 — Chilled Pork Noodle Bowl
- **Category:** Meals
- **Description (website):** A colorful bowl of chilled noodles with sliced pork, edamame, cucumber, lotus root, and sweet corn, finished with a savory sauce.
- **Image alt text:** Chilled noodles topped with pork, edamame, cucumber, lotus root, and corn.

#### 08 — Watermelon Snow Ice
- **Category:** Desserts
- **Description (website):** Light, fluffy shaved milk ice topped with juicy watermelon cubes. A refreshing little escape on a sunny afternoon.
- **Image alt text:** White shaved milk ice generously topped with red watermelon cubes.

#### 09 — Mango Snow Ice
- **Category:** Desserts
- **Description (website):** Delicate ribbons of shaved milk ice piled high with sweet mango pieces and a fruity drizzle. Bright, tropical, and irresistible.
- **Image alt text:** A bowl of yellow shaved milk ice topped with fresh mango chunks.

#### 10 — Peanut Rice Dumplings
- **Category:** Desserts
- **Description (website):** Soft and chewy glutinous rice dumplings topped with crushed peanut powder and served in a sweet sauce. A cozy Taiwanese dessert.
- **Image alt text:** White glutinous rice dumplings with peanut powder in a dark sweet sauce.

### Food-specific copy notes

- **Do not mention the original businesses by name**, in text or captions.
- All 10 titles are explicitly food names, not marketing headlines. Creative labels can appear elsewhere.
- Do not invent allergen-free claims, nutrition numbers, preparation times, or exact ingredients not confirmed by photos or the team.
- If the final photo edit reveals a food item is different from the draft description, revise the wording **before** publishing.

---

## 3. Photo asset handoff and editing checklist

### Important handoff paths

- **Companion ZIP originals:** `photos_to_edit/<original filename>` (temporary source material; do not leave branded originals in published assets)
- **After editing, place ready-to-publish copies in:** `assets/images/<final asset filename>` (per table above).
- The Markdown file **does not embed the images**. The team must also pass along the original photo files / companion ZIP so Codex can access them.
- Once the images are copied into the repository, use **relative paths** (e.g. `assets/images/mango-snow-ice.jpg`) rather than `/images/...` or `/w3images/...`. Verify the standalone `index.html` loads after the project ZIP is extracted on another computer.

### Notes by photograph

| Original filename | Action required before final publication |
|---|---|
| `IMG_8621.jpg` | Crop tightly around the Thai basil pork rice; exclude background people, if possible. |
| `IMG_8686.jpg` | Crop the foreground chicken-and-egg rice bowl; remove the person and unrelated bowl from the top of the photo. |
| `IMG_1170.jpg` | Focus on the food and soup bowl; crop out background diners, hands, and utensils if feasible. |
| `IMG_0353.jpg` | Keep the full chicken meal; inspect and remove any business-identifying lettering or logos on the napkin/placemat and utensils. |
| `IMG_1057.jpg` | Keep the foreground tomato noodle bowl; crop out background people, other bowls, and any legible business information. |
| `IMG_0817.jpg` | Trim the large empty wall area while retaining the entire bowl; this is a fairly clean composition. |
| `IMG_2141.JPG` | **Important:** Remove or obscure the restaurant name printed around the plate rim. Crop out the lower body/background distractions. Check the enlarged photo too. |
| `IMG_1348.jpg` | Crop the front watermelon snow ice bowl; remove the second bowl in the background. |
| `IMG_1347.jpg` | Already a strong, clean hero-style food shot; crop neatly around the mango snow ice. |
| `IMG_8756.jpg` | Crop around the dumplings and bowl; remove excess empty table/tray space. |

**Recommended consistency:** Use a uniform thumbnail aspect ratio (suggested `4:3` or `1:1`) with enough space to show the whole dish. Keep a higher-resolution, *also cleaned* source for lightbox enlargement. Compress reasonably for mobile. Ensure every one of the 10 cards shows **one menu item**.

### Four photos not chosen (backup only)

| Original filename | Subject | Reason for excluding from initial 10 |
|---|---|---|
| `IMG_1973.JPG` | Meat and vegetable skewers | Receipt and table clutter; possible crop needed. |
| `IMG_0040.jpg` | Layered drinks | Visible cup branding and busy background. |
| `IMG_7648.jpg` | Two soft-serve desserts | Busy background and shop-identifying details. |
| `IMG_1240.JPG` | Green soup rice with seafood and egg | Less visually striking as a menu-card thumbnail. |

---

## 4. Main navigation and filtering (English UI labels)

### Sidebar

- Logo/wordmark: **TideBites**
- Small text: **Your next study-break favorite.**
- Links: **MENU** / **ABOUT US** / **STUDENT DEALS**
- Optional callout: **Catch a wave of flavor.**
- Do not keep the original portfolio avatar or the default social icons with dead links.

### Main header and menu

- H1: **TideBites**
- Tagline: **Catch a wave of flavor.**
- Intro: **Comfort food, cool treats, and little breaks worth looking forward to.**
- Heading above cards (optional): **Explore Our Menu**
- Filtering label: **Filter:**
- Buttons: **ALL** / **MEALS** / **DESSERTS**
- Empty filter state (if needed): **No dishes match this filter. Try another category.**
- Photo enlargement prompt (optional): **Click a photo for a closer look.**
- Pagination labels: **«** / **1** / **2** / **»** for the unfiltered 10-item list.

**Implementation requirement:** Filter buttons and pagination must actually respond to clicks, rather than remaining decorative. When changing a filter, reset to page 1; recompute the correct total number of pages. For example, 10 all-items over 6 per page = 2 pages; 7 Meals = 2 pages; 3 Desserts = 1 page. Preserve the teacher-required Filter row and pagination layout.

---

## 5. About Us (English copy)

### Heading: About TideBites

**Paragraph 1**

Welcome to TideBites, a virtual restaurant created with campus life in mind. We bring together comforting rice bowls, satisfying noodles, and refreshing desserts in one easy-to-explore menu.

**Paragraph 2**

Inspired by the movement and freshness of the ocean, TideBites is all about finding a delicious pause in your day. Whether you're grabbing lunch between lectures or rewarding yourself after a long study session, there's always something worth diving into.

**Optional short ending:** No storefront, no fuss—just good food and good vibes.

### Why Choose TideBites? (four feature cards)

| Feature title | Feature description |
|---|---|
| **Made for Campus Life** | Quick to browse and easy to explore, with meals and treats for every kind of study day. |
| **Comfort in Every Bite** | From warm rice bowls to flavorful noodles, our menu brings familiar favorites together. |
| **Student-Friendly Picks** | A variety of satisfying options and proposed student deals, designed with student budgets in mind. |
| **A Taste of the Coast** | Ocean-inspired colors and a breezy experience make every visit feel like a mini escape. |

> Avoid the existing unverified promises such as “100% locally sourced,” “no preservatives,” and “every order is ready in under 15 minutes.”

### Student Favorites (replace the current Customer Reviews)

| Small feature-card title | Editorial card copy |
|---|---|
| **Lunch Between Lectures** | Need something filling? Thai Basil Pork Rice is a satisfying bowl to power you through the afternoon. |
| **A Sweet Study Break** | Treat yourself to Mango Snow Ice or Watermelon Snow Ice when you need a cool refresh. |
| **A Cozy Bowl for Any Day** | Warm up with Taiwanese Fish Ball Noodle Soup and enjoy a little comfort, one spoonful at a time. |

These are **editorial recommendations, not real customer reviews**. Do not display fake reviewer names, fake star ratings, or invented testimonials.

---

## 6. Student Deals (English copy)

### Heading: Student Deals

**Intro:** Good food should be part of the college experience. Explore our proposed student offer and discover more ways to make your next study break delicious.

### Three offer cards

| Card heading | Card body |
|---|---|
| **10% Student Discount** | Enjoy a proposed 10% discount on eligible menu items when your student status is verified. |
| **How It Works** | Student verification will be part of the future ordering experience. This Phase I website is a menu preview. |
| **Meals and Treats** | The proposed student offer includes both meals and desserts—so everyone can find a favorite. |

**Disclosure suggested for current demo:** `This is a class-project concept site. Online orders and student verification are not active.`

### Frequently Asked Questions (FAQ)

**Q1. What is TideBites?**  
TideBites is an online-only virtual restaurant concept featuring comforting meals and refreshing desserts for college students.

**Q2. Is there a physical restaurant I can visit?**  
No. TideBites is designed as a virtual restaurant focused on online browsing and food delivery rather than dining in.

**Q3. Can I place an order on this website?**  
Not yet. This Phase I website is a menu preview for our class project; online ordering is not currently available.

**Q4. How does the student discount work?**  
Our concept includes a proposed 10% student discount. Eligibility and verification steps would be finalized if online ordering is introduced.

**Q5. Do you have desserts?**  
Yes! Check out our Mango Snow Ice, Watermelon Snow Ice, and Peanut Rice Dumplings in the Desserts category.

**Q6. Can I see a larger photo of each dish?**  
Yes. Click any food photo to open a larger view. *(Only publish this answer after the enlargement feature works.)*

---

## 7. Footer (English copy)

### Footer group A — Delivery Hours

**DELIVERY HOURS** *(proposed hours for the fictional restaurant; confirm with team)*

- Monday – Friday: **11:00 – 21:00**
- Saturday: **12:00 – 21:00**
- Sunday: **12:00 – 20:00**

Small note: **Hours are part of the TideBites class-project concept.**

### Footer group B — Explore

**EXPLORE**

- **Our Menu** → `#portfolio` or updated menu anchor
- **About TideBites** → `#about`
- **Student Deals** → `#contact` or updated deals anchor

Optional blurb: **Hungry for a break? Find your next favorite bite.**

> Replace the original template's irrelevant `BLOG POSTS` section and its old photos with these menu-related links, or a text-only spotlight on actual menu items.

### Footer group C — Popular Tags

**POPULAR TAGS**

`Comfort Food` · `Rice Bowls` · `Noodles` · `Desserts` · `Snow Ice` · `Student Deals` · `Campus Life` · `Sweet Treats` · `Ocean Vibes` · `Virtual Restaurant`

### Required final attribution (MUST preserve)

`Powered by w3.css` — keep `w3.css` linked to `https://www.w3schools.com/w3css/default.asp` at the very end of the page.

---

## 8. Phase I implementation checklist for Codex

> **Copy/paste-ready request to Codex (primary task):**
>
> Work on `tingyunnnn/WEB` from the latest `main`, using a new feature branch and PR. Implement this entire Markdown spec now, without waiting for team confirmation. Do two things in the **same PR**: (1) reorganize the repository according to §0A, moving root CSS/JS/icons into `css/`, `js/`, and `assets/icons/`, keeping `index.html` at the root; (2) replace the website's current placeholder content with the TideBites brand, 10 original food photos, descriptions, About, Why Choose Us, Student Favorites, Student Deals, FAQ, and Footer specified here. Use the photo source files from `photos_to_edit/` in the ZIP. Crop/clean them as needed to exclude original restaurants' names, logos, background people, and clutter. Output publishable food images under `assets/images/` and make the enlarged view use equally clean assets. Do not generate or download replacement food photos. If you cannot safely sanitize one, flag it for manual editing and don't state the site fully passes. Use relative `css/...`, `js/...`, and `assets/...` URLs only—no leading slashes, no `/w3images/` placeholders, no build tools or local server dependency. Preserve responsive sidebar, clickable image zoom, functional All/Meals/Desserts filters, and accurate pagination (All: 6+4, Meals: 6+1, Desserts: 3), and keep the required W3.CSS attribution link at the bottom. Use an ocean-inspired responsive design, ensure all visible copy is English, and avoid fake live ordering/payment/social functions and fabricated reviews. Create/update an English README with the folder map and test steps. Verify 375px, 1024px, and 1366px widths, test `file://` opening in Chrome, and summarize the changed files, any image-cleanup blockers, testing evidence, and PR link.

### Acceptance checklist

- [ ] Replace `<title>W3.CSS Portfolio Template</title>` with the TideBites title and set description.
- [ ] Replace both `restaurant name` placeholders and all placeholder portfolio/photos/text.
- [ ] Use **exactly 10** food cards, with the exact food names, photos, descriptions, and category data in section 2.
- [ ] Add local, sanitized photos under `assets/images/` and ensure the enlarged version does not reveal original shop names/logos.
- [ ] Show **6 cards on page 1 and 4 on page 2** for the ALL filter; recompute page labels for category filters.
- [ ] Retain functional, responsive sidebar navigation (open/close behavior on mobile).
- [ ] Retain the familiar Filter layout and implement `ALL / MEALS / DESSERTS` filtering.
- [ ] Click each photo to enlarge it; add a visible/accessible close mechanism.
- [ ] Replace old About Us copy with section 5 and replace fake customer reviews with **Student Favorites**.
- [ ] Replace Student Discount copy with section 6 and ensure FAQ accurately says ordering is not active.
- [ ] Replace template footer Blog Posts/photos with section 7; preserve the required final W3.CSS attribution/link.
- [ ] No business interior/exterior photos, default portfolio avatars, or identifying information from restaurants where the food was photographed.
- [ ] No broken image paths, dead placeholder social links, or deceptive shopping/cart buttons.
- [ ] Test at viewport widths **375px, 1024px and 1366px**; no unintended horizontal overflow.
- [ ] Verify all assets load when `index.html` is opened locally after extracting the zipped project.
- [ ] Confirm final report and video screenshots reflect the actual implemented features, not just the proposal.

### Teacher-required submission reminders (team-wide)

- **Report:** PDF, **4:3 slides**, **max 20 slides**, English. Include team info, website differentiators, design choices, screenshots for 375 / 1024 / 1366px widths, member contributions, and sources.
- **Demo:** under 3 minutes, **1280 × 720**, MP4 / M4V / MOV, show all three width sizes.
- **Website source:** ZIP with `index.html`, CSS, JS, and all local images; the extracted site must open in Google Chrome on another computer.
- **Total upload limit:** 200 MB.
- **Due:** 2026-11-12 (Thu) 17:00.

### Suggested contribution text for team slides (English)

**Website Content & Photography:** Selected and organized original food photographs, created the TideBites brand-copy proposal, prepared ten English menu titles and descriptions, and drafted the About Us, Student Deals, FAQ, and footer content. Coordinated content-to-image mapping and image-cleanup requirements for website integration.

### Optional report wording for the team (only after features are actually implemented)

**Website concept:** TideBites is an ocean-inspired virtual restaurant concept designed for college students. The website combines an easy-to-browse menu, original food photography, clear categories, and approachable English descriptions to create an inviting campus-food experience.

**Design consideration:** A palette of deep ocean blue, teal, seafoam, sand, and coral was proposed to evoke the coast while keeping food photography central. Responsive layouts and consistent menu cards help visitors browse on both mobile and desktop devices.

---

## 9. Post-implementation review items (do NOT block this first implementation)

- [ ] Is **TideBites** the final brand name?
- [ ] Confirm **fish balls** in `IMG_1170.jpg`, **beef** in `IMG_0817.jpg`, and the meat/noodle type in `IMG_1057.jpg`.
- [ ] Confirm the 10 photos are original photos allowed by the course rules.
- [ ] Do we display the **proposed prices**? If not, remove them from cards entirely.
- [ ] Do we want to display **proposed delivery hours and 10% student discount**, or label them as concept-only?
- [ ] Has every final enlarged food image been cleaned of identifiable original-business details?

*End of content handoff. Keep English copy in the implemented website; Chinese/team instructions may remain in this internal document only.*
