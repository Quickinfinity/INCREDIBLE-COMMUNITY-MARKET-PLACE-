# Project: Incredible Community Marketplace

- **Created:** 2026-02-27
- **Owner:** Joe Quick
- **Repo:** https://github.com/Quickinfinity/INCREDIBLE-COMMUNITY-MARKET-PLACE-
- **Description:** Custom WordPress marketplace plugin for equipment rentals, equipment sales, and service bookings

## Objectives
- Build a single custom WordPress plugin ("JQ Marketplace Engine") with shared accounts, policies, payments, reviews, search, and admin settings
- Support three transaction types: equipment_rental, equipment_sale, service_booking
- Implement a flexible policy engine where all business rules are admin-editable, not hardcoded

## Tech Stack
- PHP 8.0+ (WordPress coding standards, OOP)
- WordPress 6.x
- WooCommerce (account/checkout foundation)
- Custom database tables (not post meta) for transactional data
- MySQL/MariaDB

## Architecture
- Plugin slug: `jq-marketplace-engine`
- Prefix: `jqme_` for functions, `JQME_` for constants
- Table prefix: `wp_jq_marketplace_`
- Neutral naming: Provider, Customer, Listing Type
- Business labels layered on top (Warrior owner-host, Certified Coatings Consultant, etc.)

## Project-Specific Rules
- Platform fee default: 9.9%
- Processing fees charged to customer
- Platform is facilitator only — never guarantor/insurer/legal judge
- Request-to-book is the default booking mode
- Equipment listings require serial verification before publishing
- Only approved providers can publish
- Mandatory two-way reviews after completed transactions
- All status transitions must be logged in audit table
- Policy profiles (not scattered booleans) for business rules
- Custom tables for moving parts; CPTs only for public-facing SEO pages if needed

## Development Order
1. Plugin scaffold + activation/deactivation
2. Database schema + migrations
3. Roles/capabilities
4. Settings/policy engine
5. Provider profiles
6. Listing CRUD + moderation
7. Booking/order engine
8. Payment abstractions
9. Claims and reviews
10. Admin screens and provider dashboards
