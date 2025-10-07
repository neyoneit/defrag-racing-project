use mysql::*;
use mysql::prelude::*;
use serde::{Deserialize, Serialize};
use std::collections::HashMap;
use rayon::prelude::*;

// Constants from CalculateRatings.php
const MIN_MAP_TOTAL_PARTICIPATORS: usize = 5;
const MIN_TOP1_TIME: i32 = 500;
const MIN_TOP_RELTIME: f64 = 0.6;
const MIN_TOTAL_RECORDS: usize = 10;
const CFG_A: f64 = 1.5;
const CFG_B: f64 = 2.086;
const CFG_M: f64 = 0.3;
const CFG_V: f64 = 0.1;
const CFG_Q: f64 = 0.5;
const CFG_D: f64 = 0.02;

#[derive(Debug, Clone)]
struct Record {
    mdd_id: i32,
    user_id: Option<i32>,
    name: String,
    mapname: String,
    physics: String,
    mode: String,
    time: i32,
    date_set: String,
}

#[derive(Debug, Clone)]
struct ProcessedRecord {
    record: Record,
    reltime: f64,
    map_score: f64,
    weight: f64,
}

#[derive(Debug, Clone, Serialize)]
struct PlayerRating {
    name: String,
    mdd_id: i32,
    user_id: Option<i32>,
    physics: String,
    mode: String,
    category: String,
    all_players_rank: usize,
    active_players_rank: usize,
    category_total_participators: usize,
    player_records_in_category: usize,
    last_activity: String,
    player_rating: f64,
}

#[derive(Debug, Clone)]
struct MapInfo {
    name: String,
    weapons: String,
    functions: String,
}

fn calculate_map_score(reltime: f64) -> f64 {
    // map_score = 1000 * (CFG_A + (-CFG_A / (1 + CFG_Q * exp(-CFG_B * (reltime - CFG_M)))^(1/CFG_V)))
    1000.0 * (CFG_A + (-CFG_A / (1.0 + CFG_Q * (-CFG_B * (reltime - CFG_M)).exp()).powf(1.0 / CFG_V)))
}

fn map_matches_category(map: &MapInfo, category: &str) -> bool {
    match category {
        "overall" => true, // All maps
        "rocket" | "rl" => map.weapons.contains("rl"),
        "plasma" | "pg" => map.weapons.contains("pg"),
        "grenade" | "gl" => map.weapons.contains("gl"),
        "bfg" => map.weapons.contains("bfg"),
        "slick" => map.functions.contains("slick"),
        "tele" => map.functions.contains("tele"),
        "strafe" => {
            // Strafe maps: no weapons OR only mg/sg/gt
            let weapons_lower = map.weapons.to_lowercase();
            if weapons_lower.is_empty() {
                return true;
            }
            // Only allowed weapons are mg, sg, gt
            !weapons_lower.split(',')
                .any(|w| {
                    let w = w.trim();
                    !w.is_empty() && w != "mg" && w != "sg" && w != "gt"
                })
        },
        _ => true,
    }
}

fn main() -> Result<()> {
    let args: Vec<String> = std::env::args().collect();
    if args.len() < 3 {
        eprintln!("Usage: {} <physics> <mode> [category]", args[0]);
        std::process::exit(1);
    }

    let physics = &args[1];
    let mode = &args[2];
    let category = if args.len() > 3 { &args[3] } else { "overall" };

    println!("Calculating ratings for {} {} [{}]...", physics, mode, category);
    let start = std::time::Instant::now();

    // Connect to MySQL (read from env or use defaults)
    let db_host = std::env::var("DB_HOST").unwrap_or_else(|_| "mysql".to_string());
    let db_user = std::env::var("DB_USERNAME").unwrap_or_else(|_| "sail".to_string());
    let db_pass = std::env::var("DB_PASSWORD").unwrap_or_else(|_| "password".to_string());
    let db_name = std::env::var("DB_DATABASE").unwrap_or_else(|_| "defrag-racing-project".to_string());

    let url = format!("mysql://{}:{}@{}/{}", db_user, db_pass, db_host, db_name);
    let opts = mysql::Opts::from_url(&url).expect("Invalid MySQL URL");
    let pool = Pool::new(opts)?;
    let mut conn = pool.get_conn()?;

    // Step 0: Load maps for category filtering
    println!("Step 0: Loading maps from database...");
    let maps: Vec<MapInfo> = conn.query_map(
        "SELECT name, weapons, functions FROM maps",
        |(name, weapons, functions)| {
            MapInfo { name, weapons, functions }
        },
    )?;
    let maps_map: HashMap<String, MapInfo> = maps.into_iter()
        .map(|m| (m.name.clone(), m))
        .collect();
    println!("  Loaded {} maps", maps_map.len());

    // Step 1: Load all records for this physics/mode
    println!("Step 1: Loading records from database...");
    let records: Vec<Record> = conn.query_map(
        &format!("SELECT mdd_id, user_id, name, mapname, physics, mode, time, DATE_FORMAT(date_set, '%Y-%m-%d %H:%i:%s') as date_set
         FROM records
         WHERE physics = '{}' AND mode = '{}' AND deleted_at IS NULL", physics, mode),
        |(mdd_id, user_id, name, mapname, physics, mode, time, date_set)| {
            Record { mdd_id, user_id, name, mapname, physics, mode, time, date_set }
        },
    )?;

    println!("  Loaded {} records", records.len());

    // Step 1.5: Filter records by category
    let filtered_records: Vec<Record> = records.into_iter()
        .filter(|record| {
            maps_map.get(&record.mapname)
                .map(|map| map_matches_category(map, category))
                .unwrap_or(false)
        })
        .collect();
    println!("  Filtered to {} records for category '{}'", filtered_records.len(), category);

    // Step 2: Group by map and calculate top times
    println!("Step 2: Calculating map statistics...");
    let mut map_stats: HashMap<String, (Vec<i32>, usize)> = HashMap::new();

    for record in &filtered_records {
        let entry = map_stats.entry(record.mapname.clone()).or_insert((Vec::new(), 0));
        entry.0.push(record.time);
        entry.1 += 1;
    }

    // Sort times per map
    for (_, (times, _)) in map_stats.iter_mut() {
        times.sort_unstable();
    }

    // Step 3: Process records in parallel - calculate reltime and map_score
    println!("Step 3: Processing records in parallel...");
    let processed: Vec<ProcessedRecord> = filtered_records.par_iter()
        .filter_map(|record| {
            let (times, total_participators) = map_stats.get(&record.mapname)?;

            // Check if map is banned
            if *total_participators < MIN_MAP_TOTAL_PARTICIPATORS {
                return None;
            }

            let top1_time = times[0].max(MIN_TOP1_TIME);
            let top2_time = if times.len() > 1 { times[1].max(MIN_TOP1_TIME) } else { top1_time };

            // Calculate reltime
            let reltime = if record.time == top1_time {
                record.time as f64 / top2_time as f64
            } else {
                record.time as f64 / top1_time as f64
            };

            // Check top_reltime
            let top_reltime = times[0] as f64 / top1_time as f64;
            if top_reltime < MIN_TOP_RELTIME {
                return None;
            }

            // Calculate map_score
            let map_score = calculate_map_score(reltime);

            Some(ProcessedRecord {
                record: record.clone(),
                reltime,
                map_score,
                weight: 0.0, // Will calculate in next step
            })
        })
        .collect();

    println!("  Processed {} valid records", processed.len());

    // Step 4: Group by player and calculate weighted ratings
    println!("Step 4: Calculating player ratings...");
    let mut player_records: HashMap<i32, Vec<ProcessedRecord>> = HashMap::new();

    for mut proc_rec in processed {
        player_records.entry(proc_rec.record.mdd_id)
            .or_insert_with(Vec::new)
            .push(proc_rec);
    }

    // Calculate ratings in parallel
    let player_ratings: Vec<PlayerRating> = player_records.par_iter()
        .map(|(mdd_id, records)| {
            // Sort by map_score desc to rank player's records
            let mut sorted_records = records.clone();
            sorted_records.sort_by(|a, b| b.map_score.partial_cmp(&a.map_score).unwrap());

            // Calculate weights and weighted scores
            let mut weighted_sum = 0.0;
            let mut weight_sum = 0.0;

            for (rank, record) in sorted_records.iter().enumerate() {
                let weight = (-CFG_D * (rank as f64 + 1.0)).exp();
                weighted_sum += record.map_score * weight;
                weight_sum += weight;
            }

            // Calculate final rating
            let mut rating = weighted_sum / weight_sum;

            // Apply penalty if less than MIN_TOTAL_RECORDS
            if records.len() < MIN_TOTAL_RECORDS {
                rating *= records.len() as f64 / MIN_TOTAL_RECORDS as f64;
            }

            // Get last activity
            let last_activity = records.iter()
                .map(|r| r.record.date_set.clone())
                .max()
                .unwrap();

            PlayerRating {
                name: records[0].record.name.clone(),
                mdd_id: *mdd_id,
                user_id: records[0].record.user_id,
                physics: records[0].record.physics.clone(),
                mode: records[0].record.mode.clone(),
                category: category.to_string(),
                all_players_rank: 0,
                active_players_rank: 0,
                category_total_participators: 0,
                player_records_in_category: records.len(),
                last_activity,
                player_rating: rating,
            }
        })
        .collect();

    // Step 5: Sort and assign ranks
    println!("Step 5: Assigning ranks...");
    let mut sorted_ratings = player_ratings;
    sorted_ratings.sort_by(|a, b| b.player_rating.partial_cmp(&a.player_rating).unwrap());

    let total_participators = sorted_ratings.len();
    let three_months_ago = chrono::Utc::now().format("%Y-%m-%d %H:%M:%S").to_string();

    let mut final_ratings: Vec<PlayerRating> = sorted_ratings.into_iter()
        .enumerate()
        .map(|(idx, mut rating)| {
            rating.all_players_rank = idx + 1;
            rating.category_total_participators = total_participators;
            rating
        })
        .collect();

    // Calculate active player ranks (filter by last 3 months activity)
    let three_months_ago = chrono::Utc::now() - chrono::Duration::days(90);
    let three_months_ago_str = three_months_ago.format("%Y-%m-%d %H:%M:%S").to_string();

    let mut active_ratings: Vec<_> = final_ratings.iter()
        .filter(|r| r.last_activity >= three_months_ago_str)
        .cloned()
        .collect();

    active_ratings.sort_by(|a, b| {
        b.player_rating.partial_cmp(&a.player_rating).unwrap()
    });

    for (idx, active_rating) in active_ratings.iter().enumerate() {
        if let Some(rating) = final_ratings.iter_mut().find(|r| r.mdd_id == active_rating.mdd_id) {
            rating.active_players_rank = idx + 1;
        }
    }

    // Step 6: Save to database
    println!("Step 6: Saving to database...");

    // Delete existing ratings for this physics/mode/category
    conn.query_drop(&format!(
        "DELETE FROM player_ratings WHERE physics = '{}' AND mode = '{}' AND category = '{}'",
        physics, mode, category
    ))?;

    // Batch insert new ratings
    for rating in &final_ratings {
        conn.query_drop(&format!(
            "INSERT INTO player_ratings
             (name, mdd_id, user_id, physics, mode, category, all_players_rank, active_players_rank,
              category_total_participators, player_records_in_category, last_activity, player_rating,
              created_at, updated_at)
             VALUES ('{}', {}, {}, '{}', '{}', '{}', {}, {}, {}, {}, '{}', {}, NOW(), NOW())",
            rating.name.replace("'", "''"),
            rating.mdd_id,
            rating.user_id.map_or("NULL".to_string(), |id| id.to_string()),
            rating.physics,
            rating.mode,
            rating.category,
            rating.all_players_rank,
            rating.active_players_rank,
            rating.category_total_participators,
            rating.player_records_in_category,
            rating.last_activity,
            rating.player_rating,
        ))?;
    }

    let duration = start.elapsed();
    println!("✓ Completed in {:.2}s", duration.as_secs_f64());
    println!("  Total players: {}", final_ratings.len());
    println!("  Total rankings calculated: {}", final_ratings.len());

    // Output JSON summary
    let summary = serde_json::json!({
        "success": true,
        "duration_seconds": duration.as_secs_f64(),
        "total_players": final_ratings.len(),
        "physics": physics,
        "mode": mode,
        "category": category,
    });

    println!("\n{}", serde_json::to_string_pretty(&summary).unwrap());

    Ok(())
}
