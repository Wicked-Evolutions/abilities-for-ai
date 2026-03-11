# FluentCRM — AI Knowledge Doc

> FluentCRM is a self-hosted CRM and email marketing system built into WordPress. It manages contacts, segments them with tags and lists, and automates communication through campaigns and sequences. This doc teaches you the domain so you can guide a human through their CRM data and help them use it effectively.

---

## What Is a CRM and Why Does It Matter?

A CRM (Customer Relationship Management) system tracks every person who interacts with a business — subscribers, leads, customers, community members. For a small business or solo creator, the CRM is the single source of truth for "who are my people and what's my relationship with them?"

FluentCRM is different from cloud CRMs (Mailchimp, HubSpot, ActiveCampaign) because:
- **Data stays on the WordPress site** — no third-party owns the contact list
- **No per-contact pricing** — the cost is the plugin license, not per subscriber
- **Deep WordPress integration** — contacts link to WP users, form submissions, orders, community membership
- **Automations run on the server** — email sequences, tagging rules, and funnels are self-hosted

This matters because the human you're helping **owns their data**. Help them understand that power.

---

## Key Concepts

### Contacts

A contact is a person. Every contact has:
- **Email** (required, unique identifier)
- **Name** (first, last)
- **Status**: `subscribed`, `unsubscribed`, `pending`, `bounced`, `complained`
  - Only `subscribed` contacts receive emails. This is legally important (GDPR, CAN-SPAM).
  - Never bulk-change status to `subscribed` without the human confirming opt-in consent.
- **Contact type**: `lead` or `customer` (can be extended)
- **Custom fields** — site-specific data (phone, company, location, etc.)
- **Source** — how they entered the CRM (form, import, WooCommerce, manual)

**Important:** A contact is NOT the same as a WordPress user. Some contacts are also WP users (they have a `user_id`), many are not. Don't confuse the two systems.

### Tags

Tags are labels you attach to contacts. They're freeform and flat (no hierarchy).

**Tags represent behaviors, interests, or attributes:**
- "downloaded-ebook" — what they did
- "interested-in-coaching" — what they want
- "vip" — what they are
- "webinar-2026-03" — what event they attended

Tags are the primary segmentation tool. Help the human think about tags as **descriptive labels that tell a story** about each contact's journey.

### Lists

Lists are groups that contacts belong to. Unlike tags, lists typically represent **subscription choices**:
- "Weekly Newsletter" — they opted into this
- "Product Updates" — they want these
- "Course Students" — they belong to this cohort

**Tags vs Lists:** Tags describe the contact. Lists represent their subscriptions/memberships. A contact tagged "interested-in-coaching" on the "Weekly Newsletter" list is someone who reads the newsletter and has shown interest in coaching.

### Segments (Dynamic)

Segments are saved filters — they don't store contacts, they define rules that match contacts in real time:
- "All subscribed contacts tagged 'vip' who opened an email in the last 30 days"
- "Contacts on the 'Course Students' list who haven't clicked anything in 60 days"

Segments are powerful for finding patterns. When the human wants to understand their audience, segments are the tool.

---

## Email Marketing Concepts

### Campaigns

A campaign is a **one-time email** sent to a specific audience (list, tag, segment). Like a newsletter blast.

Key fields:
- **Subject line** and **preview text** (what shows in inbox)
- **Email body** — supports block editor or classic HTML
- **Recipients** — defined by lists, tags, or segments (include AND exclude)
- **Schedule** — send immediately or at a future date/time

### Sequences (Automation Emails)

A sequence is a **series of emails sent over time** — drip campaigns. A contact enters the sequence and receives emails on a schedule:
- Day 0: Welcome email
- Day 2: Your story
- Day 5: First offer
- Day 7: Follow-up

Key difference from campaigns: campaigns are sent once to many. Sequences are triggered per-contact and drip over time.

**Sequence vs Campaign decision:** If the human asks to "send an email to all subscribers" → campaign. If they ask to "send a welcome series when someone signs up" → sequence.

---

## How Things Connect

```
Forms (FluentForms) ──submit──→ Contact created/updated + Tags applied
                                    │
                                    ├──→ Added to List(s)
                                    ├──→ Enters Sequence (drip emails)
                                    └──→ Automation triggered

WooCommerce/FluentCart ──purchase──→ Contact tagged + type → "customer"

Community (FluentCommunity) ──join──→ Contact tagged "community-member"

Booking (FluentBooking) ──booked──→ Contact tagged + calendar event
```

Everything flows into the CRM. The CRM is the hub. **Help the human see their CRM as the central nervous system of their business.**

---

## Common Patterns (What People Actually Do)

### 1. "I want to see my subscriber overview"
→ `fluent-crm/list-contacts` with status filter
→ `fluent-crm/list-tags` to see what tags exist
→ `fluent-crm/list-lists` to see subscription lists
→ Tell the human: total subscribers, top tags, list sizes, recent growth

### 2. "I want to send a newsletter"
→ First: confirm they have subscribers (check lists/segments)
→ Create campaign with subject, body, recipients
→ Suggest: preview/test before sending, check schedule timezone

### 3. "I want to set up a welcome sequence"
→ This is a sequence, not a campaign
→ Plan the emails: what to say on day 0, 3, 5, 7
→ Connect it to a form or list (trigger: when contact added to list X)

### 4. "I want to understand my audience"
→ Use segments to find patterns
→ Tag analysis: which tags are most common? Which combinations?
→ Engagement: who opened recent campaigns? Who hasn't engaged in 30/60/90 days?

### 5. "I want to clean up my contact list"
→ Find bounced/complained contacts → review for removal
→ Find unengaged contacts (no opens in 90 days) → re-engagement campaign or remove
→ **Always ask before deleting contacts.** Data loss is permanent.

---

## Pitfalls

1. **Sending to unsubscribed contacts** — Never. FluentCRM enforces this, but make sure campaign targeting only includes `subscribed` status.
2. **No double opt-in** — If the site is in the EU or targets EU residents, double opt-in is effectively required. Check and advise.
3. **Tag sprawl** — Too many tags with no naming convention becomes chaos. Suggest conventions: `source-*`, `interest-*`, `event-*`, `product-*`.
4. **Sending limits** — Self-hosted email has sending limits. Ask about their SMTP setup if planning a large send.
5. **Contact vs user confusion** — The human may say "users" when they mean "contacts." Clarify.

---

## Available Abilities

### Contact Management
| Ability | What it does |
|---------|-------------|
| `fluent-crm/list-contacts` | List/search contacts with filters (status, tag, list, date, search) |
| `fluent-crm/get-contact` | Full contact details including tags, lists, custom fields |
| `fluent-crm/create-contact` | Add a new contact (email required) |
| `fluent-crm/update-contact` | Update contact fields, status, tags, lists |
| `fluent-crm/delete-contact` | Remove a contact — **confirm with human first** |

### Tags and Lists
| Ability | What it does |
|---------|-------------|
| `fluent-crm/list-tags` | All tags with contact counts |
| `fluent-crm/create-tag` | Create a new tag |
| `fluent-crm/list-lists` | All lists with subscriber counts |
| `fluent-crm/create-list` | Create a new list |

### Campaigns and Sequences
| Ability | What it does |
|---------|-------------|
| `fluent-crm/list-campaigns` | All campaigns with status and stats |
| `fluent-crm/get-campaign` | Campaign details including open/click rates |
| `fluent-crm/create-campaign` | Create a new email campaign |
| `fluent-crm/list-sequences` | All sequences with status |
| `fluent-crm/get-sequence` | Sequence details and subscriber count |

### Templates
| Ability | What it does |
|---------|-------------|
| `fluent-crm/list-templates` | Email templates |

---

## Suggested Dialogue

**If they've never used their CRM:**
> "Your site has FluentCRM installed — it's tracking everyone who's interacted with your site. Want me to show you who's in your contact list? We can look at how many subscribers you have, what tags they've been given, and what lists they're on."

**If they want to do something specific:**
> "Before we do that, let me check your current CRM state — how many contacts you have, what segments exist, and what's been sent recently. That way we can build on what's already there."

**If they're overwhelmed:**
> "Let's start simple. The three things that matter most right now are: (1) how many subscribed contacts you have, (2) how they're organized with tags and lists, and (3) whether you've been communicating with them. Want me to pull those numbers?"

---

*Source: FluentCRM documentation and Abilities for Fluent Plugins registry.*
