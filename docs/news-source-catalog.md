# News source catalog

Verified July 23, 2026.

## Automatically published official source

- NOAA National Hurricane Center — Atlantic RSS
  - Feed: `https://www.nhc.noaa.gov/index-at.xml`
  - Coverage: Atlantic tropical weather and advisories
  - Images and full text are not copied

## Official sources requiring editorial review

- U.S. Department of State Travel Advisories
  - Feed: `https://travel.state.gov/_res/rss/TAsTWs.xml`
  - Import filter: Caribbean destinations only
- Hyatt Inclusive Collection Newsroom
  - Feed: `https://newsroom.hyatt.com/news-releases?category=827&pagetemplate=rss`
- Hilton Stories
  - Feed: `https://stories.hilton.com/feed`

## Trade and specialist sources requiring editorial review

- Cruise Industry News
  - Feed: `https://cruiseindustrynews.com/cruise-news/feed/`
- Cruise Hive
  - Feed: `https://www.cruisehive.com/feed`
- Caribbean Journal
  - Feed: `https://www.caribjournal.com/feed/`
- PR Newswire Travel
  - Feed: `https://www.prnewswire.com/rss/travel-latest-news/travel-latest-news-list.rss`
  - Import filter: cruise, resort, hotel, Caribbean, tourism, and advisory topics

## Editorial policy

- Store and display an original short summary, attribution, publication date, and source link.
- Do not import full copyrighted article text.
- Do not reuse source images unless permission is separately documented and approved.
- Commercial, trade, and broad government feeds remain unpublished until an administrator reviews them.
- Automatic publication is limited to an explicitly approved official source that has
  `is_official_source = 1`, `requires_manual_review = 0`, and a relevance score of at least 60.

## Local setup and scheduling

Configure or refresh the source catalog:

```bash
/Applications/MAMP/bin/php/php8.4.17/bin/php database/migrations/2026_07_23_seed_news_sources.php
```

Run all sources that are due:

```bash
/Applications/MAMP/bin/php/php8.4.17/bin/php scripts/import_news.php
```

For production, schedule `scripts/import_news.php` every 15 minutes with the hosting
control panel. Each source's own `fetch_frequency_minutes` value prevents unnecessary requests.
