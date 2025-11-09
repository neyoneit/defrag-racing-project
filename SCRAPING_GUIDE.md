# Record Scraping Guide

This guide explains how to scrape and process records from Q3DF into the database.

## Overview

The scraping system uses a two-process architecture:
1. **Fetcher** - Scrapes records from Q3DF and queues them
2. **Processor** - Takes queued records and inserts them into the database

This separation provides better memory efficiency, resumability, and allows parallel operation.

## Quick Start

### Fresh Scrape (from scratch)

```bash
# 1. Reset scraper data (clears progress, pages, and queue)
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:reset --force

# 2. Start the fetcher (scrapes pages and queues records)
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:records-fetch-new

# 3. In a separate terminal, start the processor (processes queued records)
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:records-process-new --continuous
```

### Resume After Error

If the scraper crashes or times out, simply restart it - it will automatically resume from where it left off:

```bash
# The fetcher automatically resumes from the last page
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:records-fetch-new

# The processor continues processing remaining queued records
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:records-process-new --continuous
```

## Available Commands

### scrape:records-fetch-new

Fetches records from Q3DF and queues them for processing.

**Features:**
- Auto-resumes from errors
- Detects duplicate pages to find the end
- Auto-retries with exponential backoff on network timeouts
- Handles malformed records gracefully

**Options:**
```bash
# Start from a specific page
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:records-fetch-new --from-page=1000

# Change duplicate page detection threshold
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:records-fetch-new --same-page-threshold=3
```

**What it does:**
- Scrapes pages from Q3DF one by one
- Generates a fingerprint for each page to detect duplicates
- Queues all records from each page
- Tracks progress in the database
- Stops when it sees the same page content 3 times consecutively

### scrape:records-process-new

Processes queued records and inserts them into the database.

**Features:**
- Idempotent (safe to run multiple times)
- Handles duplicates automatically
- Moves improved records to history
- Retries failed records up to 3 times

**Options:**
```bash
# Process in batches of 100 (default)
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:records-process-new --batch-size=100

# Run continuously (recommended)
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:records-process-new --continuous
```

**What it does:**
- Checks for duplicate records (same player, map, physics, mode)
- If time is the same → skips (exact duplicate)
- If time is different → moves old record to history, inserts new one
- Associates records with users by MDD ID
- Updates page status when all records from a page are processed

### scrape:status

Shows current scraper progress and queue statistics.

```bash
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:status
```

**Output includes:**
- Current scraper status and page
- Total records scraped
- Pages processed vs queued
- Queue statistics (pending, processing, completed, failed)
- Recent failed records (if any)

### scrape:reset

Resets all scraper progress and queued data.

```bash
# With confirmation prompt
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:reset

# Skip confirmation
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:reset --force
```

**Warning:** This clears:
- Scraper progress tracking
- Scraped pages tracking
- Queued records

**Note:** Your actual records table is NOT affected.

### scrape:kill

Kills all running scraper processes.

```bash
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:kill
```

**When to use:**
- Scrapers are stuck
- Want to stop all scraping processes
- Need to restart with different options

## Recommended Workflow

### 1. Initial Setup

```bash
# Check current status
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:status

# Reset if needed
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:reset --force
```

### 2. Start Scraping

**Terminal 1 - Fetcher:**
```bash
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:records-fetch-new
```

**Terminal 2 - Processor:**
```bash
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:records-process-new --continuous
```

**Terminal 3 - Monitor (optional):**
```bash
# Check status every 10 seconds
watch -n 10 'docker exec defrag-racing-project-laravel.test-1 php artisan scrape:status'
```

### 3. Monitor Progress

The fetcher will output:
```
[Page 1] ✓ Scraped 15 records (15 new, 0 duplicates)
[Page 2] ✓ Scraped 15 records (10 new, 5 duplicates)
...
```

The processor will output:
```
Processing batch of 100 records...
Batch complete: 100 processed, 98 inserted, 2 duplicates, 0 updated, 0 failed
```

### 4. Handle Errors

If you see a connection timeout:
```
Error on page 2031: Connection timed out
```

**Don't worry!** Just restart the fetcher:
```bash
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:records-fetch-new
```

It will automatically resume from page 2031 with built-in retry logic.

## Troubleshooting

### Problem: Process won't stop with Ctrl+C

**Solution:**
```bash
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:kill
```

### Problem: "No pending records" but pages are queued

Check if processor is running:
```bash
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:status
```

If processor is not running, start it:
```bash
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:records-process-new --continuous
```

### Problem: Many failed records

Check recent failures:
```bash
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:status
```

Failed records will automatically retry up to 3 times. If they keep failing, there may be a data issue.

### Problem: Scraper seems stuck

1. Check status:
```bash
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:status
```

2. If stuck on network timeout, it will auto-retry. Wait for the retry countdown.

3. If truly stuck, kill and restart:
```bash
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:kill
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:records-fetch-new
```

## Performance Tips

### Batch Size

Adjust batch size based on system resources:
- **Small batches (50-100)**: Lower memory, more frequent status updates
- **Large batches (500-1000)**: Faster processing, higher memory usage

```bash
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:records-process-new --batch-size=500 --continuous
```

### Parallel Processing

You can run multiple processors in parallel for faster processing:

**Terminal 1:**
```bash
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:records-process-new --continuous
```

**Terminal 2:**
```bash
docker exec defrag-racing-project-laravel.test-1 php artisan scrape:records-process-new --continuous
```

## Database Tables

The scraper uses three tracking tables:

1. **scraper_progress** - Overall progress and status
2. **scraped_pages** - Individual page tracking with fingerprints
3. **scraped_records_queue** - Queue of records to process

These are separate from your main `records` table for safety.

## Auto-Retry Feature

The scraper automatically retries failed connections:
- **1st retry**: Wait 60 seconds
- **2nd retry**: Wait 120 seconds (2 minutes)
- **3rd retry**: Wait 240 seconds (4 minutes)
- **4th retry**: Wait 480 seconds (8 minutes)
- **5th attempt**: Fail and crash (very unlikely)

This means the scraper can handle temporary network issues automatically without manual intervention.

## Example Output

### Successful Scraping

```
Starting scraper from page 1...
Will stop after seeing same page content 3 times

[Page 1] Scraping...
[Page 1] ✓ Scraped 15 records (15 new, 0 duplicates)
[Page 2] Scraping...
[Page 2] ✓ Scraped 15 records (15 new, 0 duplicates)
...
```

### Network Timeout with Auto-Retry

```
[Page 2031] Scraping...
Scraping page: 2031
  ⚠️  Connection failed (attempt 1/5). Retrying in 60 seconds...
Scraping page: 2031
[Page 2031] ✓ Scraped 15 records (15 new, 0 duplicates)
```

### Completion

```
========================================
SCRAPER STOPPED
========================================
Reason: Detected same page content 3 times consecutively. Actual last page: 15234
Last valid page: 15234
Total records scraped: 228,510
========================================
```

## Notes

- The scraper is **idempotent** - safe to run multiple times
- All operations are **resumable** - can stop and restart anytime
- **Duplicate detection** prevents inserting the same record twice
- **History tracking** preserves old times when records improve
- **Network resilience** with automatic retries and exponential backoff
