use mysql::*;
use mysql::prelude::*;
use serde::Serialize;
use std::collections::HashMap;
use rayon::prelude::*;

// Constants - read from environment variables (shared with PHP via .env)
// Fallback defaults match config/ratings.php
#[allow(dead_code)]
const OUTLIER_THRESHOLD: f64 = 0.6; // ratio below this = outlier detected (currently disabled)


struct RatingConfig {
    min_map_total_participators: usize,
    min_top1_time: i32,
    max_tied_wr_players: usize,
    min_total_records: usize,
    cfg_a: f64,
    cfg_b: f64,
    cfg_m: f64,
    cfg_v: f64,
    cfg_q: f64,
    cfg_d: f64,
    mult_l: f64,
    mult_n: f64,
    rank_n: f64,
    rank_v: f64,
}

impl RatingConfig {
    fn from_db(conn: &mut PooledConn) -> Self {
        let rows: Vec<(String, String)> = conn.query("SELECT `key`, `value` FROM rating_settings")
            .unwrap_or_default();
        let map: HashMap<String, String> = rows.into_iter().collect();

        let get_f64 = |key: &str, default: f64| -> f64 {
            map.get(key).and_then(|v| v.parse().ok()).unwrap_or(default)
        };
        let get_usize = |key: &str, default: usize| -> usize {
            map.get(key).and_then(|v| v.parse().ok()).unwrap_or(default)
        };
        let get_i32 = |key: &str, default: i32| -> i32 {
            map.get(key).and_then(|v| v.parse().ok()).unwrap_or(default)
        };

        Self {
            min_map_total_participators: get_usize("min_map_players", 5),
            min_top1_time: get_i32("min_top1_time", 500),
            max_tied_wr_players: get_usize("max_tied_wr_players", 3),
            min_total_records: get_usize("min_total_records", 10),
            cfg_a: get_f64("cfg_a", 1.2),
            cfg_b: get_f64("cfg_b", 1.33),
            cfg_m: get_f64("cfg_m", 0.3),
            cfg_v: get_f64("cfg_v", 0.1),
            cfg_q: get_f64("cfg_q", 0.5),
            cfg_d: get_f64("cfg_d", 0.02),
            mult_l: get_f64("mult_l", 1.0),
            mult_n: get_f64("mult_n", 2.0),
            rank_n: get_f64("rank_exponent", 1.5),
            rank_v: get_f64("rank_v", 2.0),
        }
    }
}

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
    multiplier: f64,
    rank_multiplier: f64,
    is_outlier: bool,
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

#[allow(dead_code)]
struct MapStats {
    times: Vec<i32>,
    mdd_ids: Vec<i32>,
    total_participators: usize,
    ref_index: usize,
    is_ranked: bool,
}

/// Find where the "normal" cluster starts by scanning consecutive time ratios.
/// Returns the index of the first time in the normal cluster.
/// DISABLED: outlier normalization commented out - rank 1 and 2 get same reward which is wrong
fn find_normal_cluster_start(_times: &[i32]) -> usize {
    return 0;
    // if times.len() < 2 {
    //     return 0;
    // }
    // for i in 0..times.len() - 1 {
    //     let ratio = times[i] as f64 / times[i + 1] as f64;
    //     if ratio >= OUTLIER_THRESHOLD {
    //         return i;
    //     }
    // }
    // times.len() - 1
}

fn calculate_map_score(reltime: f64, cfg: &RatingConfig) -> f64 {
    1000.0 * (cfg.cfg_a + (-cfg.cfg_a / (1.0 + cfg.cfg_q * (-cfg.cfg_b * (reltime - cfg.cfg_m)).exp()).powf(1.0 / cfg.cfg_v)))
}

/// Rank-based multiplier: rewards higher ranks on a map.
/// rank_multiplier = (((total_players * v) - your_rank) / ((total_players * v) - 1)) ^ n
fn calculate_rank_multiplier(total_players: usize, your_rank: usize, cfg: &RatingConfig) -> f64 {
    if cfg.rank_n == 0.0 || cfg.rank_v == 0.0 || total_players <= 1 || your_rank == 0 {
        return 1.0;
    }
    let tp = total_players as f64;
    let rank = your_rank as f64;
    let numerator = (tp * cfg.rank_v) - rank;
    let denominator = (tp * cfg.rank_v) - 1.0;
    if denominator <= 0.0 {
        return 1.0;
    }
    (numerator / denominator).max(0.0).powf(cfg.rank_n)
}

/// Map score multiplier based on number of records/players on given map.
fn calculate_map_multiplier(num_records: usize, category_median: f64, cfg: &RatingConfig) -> f64 {
    let k = (category_median / 2.0).max(1.0);
    let x = num_records as f64;
    (cfg.mult_l * x.powf(cfg.mult_n)) / (k.powf(cfg.mult_n) + x.powf(cfg.mult_n))
}

/// Calculate median of a sorted slice
fn median(sorted: &[usize]) -> f64 {
    let len = sorted.len();
    if len == 0 {
        return 0.0;
    }
    if len % 2 == 0 {
        (sorted[len / 2 - 1] + sorted[len / 2]) as f64 / 2.0
    } else {
        sorted[len / 2] as f64
    }
}

fn map_matches_category(map: &MapInfo, category: &str) -> bool {
    match category {
        "overall" => true,
        "rocket" | "rl" => map.weapons.contains("rl"),
        "plasma" | "pg" => map.weapons.contains("pg"),
        "grenade" | "gl" => map.weapons.contains("gl"),
        "bfg" => map.weapons.contains("bfg"),
        "lg" => map.weapons.contains("lg"),
        "slick" => map.functions.contains("slick"),
        "tele" => map.functions.contains("tele"),
        "strafe" => {
            let weapons_lower = map.weapons.to_lowercase();
            if weapons_lower.is_empty() {
                return true;
            }
            // These weapons are allowed on strafe maps - they don't disqualify
            !weapons_lower.split(',')
                .any(|w| {
                    let w = w.trim();
                    !w.is_empty() && w != "mg" && w != "sg" && w != "gt"
                        && w != "gauntlet" && w != "hook" && w != "rg"
                })
        },
        _ => true,
    }
}

/// Check if a map is a "free WR" map (4+ players tied at WR time)
fn is_free_wr_map(times: &[i32], mdd_ids: &[i32], cfg: &RatingConfig) -> bool {
    if times.is_empty() {
        return false;
    }
    let wr_time = times[0];
    let mut unique_wr_players: Vec<i32> = Vec::new();
    for (i, &time) in times.iter().enumerate() {
        if time != wr_time {
            break;
        }
        if !unique_wr_players.contains(&mdd_ids[i]) {
            unique_wr_players.push(mdd_ids[i]);
        }
    }
    unique_wr_players.len() > cfg.max_tied_wr_players
}

/// Process records for a map and return ProcessedRecords
/// category_median: median number of records/players across all ranked maps in this category
fn process_map_records(records: &[Record], stats: &MapStats, category_median: f64, cfg: &RatingConfig) -> Vec<ProcessedRecord> {
    if !stats.is_ranked {
        return Vec::new();
    }

    let times = &stats.times;
    let ref_index = stats.ref_index;
    let ref_time = times[ref_index].max(cfg.min_top1_time) as f64;

    records.iter().filter_map(|record| {
        let is_in_outlier_group = if ref_index > 0 {
            let mut found_outlier = false;
            for idx in 0..ref_index {
                if times[idx] == record.time {
                    found_outlier = true;
                    break;
                }
            }
            found_outlier
        } else {
            false
        };

        let reltime = if is_in_outlier_group {
            if ref_index + 1 < times.len() {
                let next_time = times[ref_index + 1].max(cfg.min_top1_time) as f64;
                ref_time / next_time
            } else {
                1.0
            }
        } else if record.time as f64 <= ref_time {
            if ref_index + 1 < times.len() {
                ref_time / times[ref_index + 1].max(cfg.min_top1_time) as f64
            } else {
                1.0
            }
        } else {
            record.time as f64 / ref_time
        };

        let base_score = calculate_map_score(reltime, cfg);
        // Apply map multiplier based on number of records/players on given map
        let multiplier = calculate_map_multiplier(stats.total_participators, category_median, cfg);

        // Apply rank multiplier: find player's rank on this map (1-indexed)
        let your_rank = times.iter().position(|&t| t == record.time).map(|p| p + 1).unwrap_or(stats.total_participators);
        let rank_mult = calculate_rank_multiplier(stats.total_participators, your_rank, cfg);

        let map_score = base_score * multiplier * rank_mult;

        Some(ProcessedRecord {
            record: record.clone(),
            reltime,
            map_score,
            multiplier,
            rank_multiplier: rank_mult,
            is_outlier: is_in_outlier_group,
        })
    }).collect()
}

/// Calculate player rating from their map scores
fn calculate_player_rating(map_scores: &mut Vec<f64>, cfg: &RatingConfig) -> f64 {
    map_scores.sort_by(|a, b| b.partial_cmp(a).unwrap());

    let mut weighted_sum = 0.0;
    let mut weight_sum = 0.0;

    for (rank, &score) in map_scores.iter().enumerate() {
        let weight = (-cfg.cfg_d * (rank as f64 + 1.0)).exp();
        weighted_sum += score * weight;
        weight_sum += weight;
    }

    let mut rating = weighted_sum / weight_sum;

    if map_scores.len() < cfg.min_total_records {
        rating *= map_scores.len() as f64 / cfg.min_total_records as f64;
    }

    rating
}

/// Build map stats from records
fn build_map_stats(records: &[Record], cfg: &RatingConfig) -> HashMap<String, MapStats> {
    let mut map_records: HashMap<String, Vec<(i32, i32)>> = HashMap::new();

    for record in records {
        map_records.entry(record.mapname.clone())
            .or_insert_with(Vec::new)
            .push((record.time, record.mdd_id));
    }

    let mut map_stats: HashMap<String, MapStats> = HashMap::new();

    for (mapname, mut time_mdd_pairs) in map_records {
        time_mdd_pairs.sort_by_key(|&(time, _)| time);
        let times: Vec<i32> = time_mdd_pairs.iter().map(|&(t, _)| t).collect();
        let mdd_ids: Vec<i32> = time_mdd_pairs.iter().map(|&(_, m)| m).collect();
        let total_participators = {
            let mut unique: Vec<i32> = mdd_ids.clone();
            unique.sort_unstable();
            unique.dedup();
            unique.len()
        };

        let top1_time = times[0];
        if total_participators < cfg.min_map_total_participators || top1_time < cfg.min_top1_time {
            map_stats.insert(mapname, MapStats {
                times, mdd_ids, total_participators, ref_index: 0, is_ranked: false,
            });
            continue;
        }

        if is_free_wr_map(&times, &mdd_ids, cfg) {
            map_stats.insert(mapname, MapStats {
                times, mdd_ids, total_participators, ref_index: 0, is_ranked: false,
            });
            continue;
        }

        let ref_index = find_normal_cluster_start(&times);

        map_stats.insert(mapname, MapStats {
            times, mdd_ids, total_participators, ref_index, is_ranked: true,
        });
    }

    map_stats
}

/// Save processed records to player_map_scores table
fn save_map_scores(conn: &mut PooledConn, processed: &[ProcessedRecord], physics: &str, mode: &str) -> Result<usize> {
    if processed.is_empty() {
        return Ok(0);
    }

    // Batch insert using multi-row INSERT ... ON DUPLICATE KEY UPDATE
    let mut saved = 0;
    for chunk in processed.chunks(500) {
        let mut values: Vec<String> = Vec::new();
        for rec in chunk {
            values.push(format!(
                "({}, {}, '{}', '{}', '{}', {}, {}, {}, {}, {}, {}, NOW(), NOW())",
                rec.record.mdd_id,
                rec.record.user_id.map_or("NULL".to_string(), |id| id.to_string()),
                rec.record.mapname.replace("'", "''"),
                physics,
                mode,
                rec.record.time,
                rec.reltime,
                rec.map_score,
                rec.multiplier,
                rec.rank_multiplier,
                if rec.is_outlier { 1 } else { 0 },
            ));
        }

        let query = format!(
            "INSERT INTO player_map_scores (mdd_id, user_id, mapname, physics, mode, time, reltime, map_score, multiplier, rank_multiplier, is_outlier, created_at, updated_at) VALUES {} ON DUPLICATE KEY UPDATE time=VALUES(time), reltime=VALUES(reltime), map_score=VALUES(map_score), multiplier=VALUES(multiplier), rank_multiplier=VALUES(rank_multiplier), is_outlier=VALUES(is_outlier), user_id=VALUES(user_id), updated_at=NOW()",
            values.join(",")
        );

        conn.query_drop(&query)?;
        saved += chunk.len();
    }

    Ok(saved)
}

/// Full recalculation mode
fn full_recalc(conn: &mut PooledConn, physics: &str, mode: &str, category: &str, maps_map: &HashMap<String, MapInfo>, cfg: &RatingConfig) -> Result<()> {
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
            maps_map.get(&record.mapname.to_lowercase())
                .map(|map| map_matches_category(map, category))
                .unwrap_or(false)
        })
        .collect();
    println!("  Filtered to {} records for category '{}'", filtered_records.len(), category);

    // Step 2: Build map stats
    println!("Step 2: Calculating map statistics...");
    let map_stats = build_map_stats(&filtered_records, cfg);

    let ranked_maps: Vec<String> = map_stats.iter()
        .filter(|(_, s)| s.is_ranked)
        .map(|(n, _)| n.clone())
        .collect();
    let outlier_count = map_stats.iter()
        .filter(|(_, s)| s.is_ranked && s.ref_index > 0)
        .count();
    let free_wr_count = map_stats.iter()
        .filter(|(_, s)| !s.is_ranked && s.total_participators >= cfg.min_map_total_participators && s.times.first().map_or(false, |&t| t >= cfg.min_top1_time))
        .count();

    println!("  Ranked maps: {}, outlier-normalized: {}, free WR excluded: {}",
        ranked_maps.len(), outlier_count, free_wr_count);

    // Step 3: Process records
    println!("Step 3: Processing records...");
    // Group records by map for processing
    let mut records_by_map: HashMap<String, Vec<Record>> = HashMap::new();
    for record in &filtered_records {
        records_by_map.entry(record.mapname.clone())
            .or_insert_with(Vec::new)
            .push(record.clone());
    }

    // Calculate median number of records/players per ranked map for this category
    // Used as the basis for the logistic map score multiplier (k = median / 2)
    let mut ranked_participators: Vec<usize> = map_stats.iter()
        .filter(|(_, s)| s.is_ranked)
        .map(|(_, s)| s.total_participators)
        .collect();
    ranked_participators.sort_unstable();
    let category_median = median(&ranked_participators);
    println!("  Ranked maps median records/players: {}, k (median/2): {:.1}", category_median, (category_median / 2.0).max(1.0));

    let mut all_processed: Vec<ProcessedRecord> = Vec::new();
    for (mapname, map_records) in &records_by_map {
        if let Some(stats) = map_stats.get(mapname) {
            let processed = process_map_records(map_records, stats, category_median, cfg);
            all_processed.extend(processed);
        }
    }

    println!("  Processed {} valid records", all_processed.len());

    // Step 3.5: Save map scores to player_map_scores (only for 'overall' to avoid duplicates)
    if category == "overall" {
        println!("Step 3.5: Saving map scores to player_map_scores...");
        // Clear old scores for this physics/mode
        conn.query_drop(&format!(
            "DELETE FROM player_map_scores WHERE physics = '{}' AND mode = '{}'",
            physics, mode
        ))?;
        let saved = save_map_scores(conn, &all_processed, physics, mode)?;
        println!("  Saved {} map scores", saved);
    }

    // Step 4: Group by player and calculate weighted ratings
    println!("Step 4: Calculating player ratings...");
    let mut player_records: HashMap<i32, Vec<ProcessedRecord>> = HashMap::new();

    for proc_rec in all_processed {
        player_records.entry(proc_rec.record.mdd_id)
            .or_insert_with(Vec::new)
            .push(proc_rec);
    }

    let player_ratings: Vec<PlayerRating> = player_records.par_iter()
        .map(|(mdd_id, records)| {
            let mut map_scores: Vec<f64> = records.iter().map(|r| r.map_score).collect();
            let rating = calculate_player_rating(&mut map_scores, cfg);

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

    let mut final_ratings: Vec<PlayerRating> = sorted_ratings.into_iter()
        .enumerate()
        .map(|(idx, mut rating)| {
            rating.all_players_rank = idx + 1;
            rating.category_total_participators = total_participators;
            rating
        })
        .collect();

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

    conn.query_drop(&format!(
        "DELETE FROM player_ratings WHERE physics = '{}' AND mode = '{}' AND category = '{}'",
        physics, mode, category
    ))?;

    for rating in &final_ratings {
        conn.query_drop(&format!(
            "INSERT INTO player_ratings
             (name, mdd_id, user_id, physics, mode, category, all_players_rank, active_players_rank,
              category_total_participators, player_records_in_category, last_activity, player_rating,
              created_at, updated_at)
             VALUES ('{}', {}, {}, '{}', '{}', '{}', {}, {}, {}, {}, '{}', {}, NOW(), NOW())
             ON DUPLICATE KEY UPDATE name=VALUES(name), user_id=VALUES(user_id),
              all_players_rank=VALUES(all_players_rank), active_players_rank=VALUES(active_players_rank),
              category_total_participators=VALUES(category_total_participators),
              player_records_in_category=VALUES(player_records_in_category),
              last_activity=VALUES(last_activity), player_rating=VALUES(player_rating), updated_at=NOW()",
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

    // Step 7: Update ranked flags on maps table (only for 'overall' category in 'run' mode)
    if category == "overall" && mode == "run" {
        println!("Step 7: Updating map ranked flags...");
        let column = format!("is_ranked_{}", physics);

        conn.query_drop(&format!("UPDATE maps SET {} = 0", column))?;

        if !ranked_maps.is_empty() {
            let placeholders: Vec<String> = ranked_maps.iter()
                .map(|m| format!("'{}'", m.replace("'", "''")))
                .collect();
            let in_clause = placeholders.join(",");
            conn.query_drop(&format!(
                "UPDATE maps SET {} = 1 WHERE name IN ({})", column, in_clause
            ))?;
        }

        let free_wr_excluded: Vec<String> = map_stats.iter()
            .filter(|(_, s)| !s.is_ranked && s.total_participators >= cfg.min_map_total_participators && s.times.first().map_or(false, |&t| t >= cfg.min_top1_time))
            .map(|(n, _)| n.clone())
            .collect();

        println!("  Updated {}: {} ranked, {} free WR excluded",
            column, ranked_maps.len(), free_wr_excluded.len());
    }

    println!("  Total players: {}", final_ratings.len());
    println!("  Total rankings calculated: {}", final_ratings.len());

    Ok(())
}

/// Incremental recalculation for a single map
fn incremental_recalc(conn: &mut PooledConn, physics: &str, mode: &str, map_name: &str, maps_map: &HashMap<String, MapInfo>, cfg: &RatingConfig) -> Result<()> {
    println!("Incremental recalc for map '{}' ({} {})...", map_name, physics, mode);

    // Step 1: Load records for this map only
    let records: Vec<Record> = conn.query_map(
        &format!("SELECT mdd_id, user_id, name, mapname, physics, mode, time, DATE_FORMAT(date_set, '%Y-%m-%d %H:%i:%s') as date_set
         FROM records
         WHERE physics = '{}' AND mode = '{}' AND mapname = '{}' AND deleted_at IS NULL",
         physics, mode, map_name.replace("'", "''")),
        |(mdd_id, user_id, name, mapname, physics, mode, time, date_set)| {
            Record { mdd_id, user_id, name, mapname, physics, mode, time, date_set }
        },
    )?;

    println!("  Loaded {} records for map", records.len());

    if records.is_empty() {
        println!("  No records found, skipping.");
        return Ok(());
    }

    // Step 2: Build map stats for this map
    let map_stats = build_map_stats(&records, cfg);
    let stats = match map_stats.get(map_name) {
        Some(s) => s,
        None => {
            println!("  Map not found in stats, skipping.");
            return Ok(());
        }
    };

    if !stats.is_ranked {
        println!("  Map is not ranked (insufficient players, min time, or free WR). Removing old scores.");
        conn.query_drop(&format!(
            "DELETE FROM player_map_scores WHERE mapname = '{}' AND physics = '{}' AND mode = '{}'",
            map_name.replace("'", "''"), physics, mode
        ))?;
        return Ok(());
    }

    if stats.ref_index > 0 {
        println!("  Outlier detected: {} outlier player(s), ref_index={}", stats.ref_index, stats.ref_index);
    }

    // Step 3: Calculate category median for map multiplier
    // For incremental recalc, query median number of records/players per ranked map from DB
    // We use the same physics+mode; category filtering would require map info which we skip here for performance
    let all_map_counts: Vec<i64> = conn.query(&format!(
        "SELECT COUNT(DISTINCT mdd_id) as cnt FROM records WHERE physics = '{}' AND mode = '{}' AND deleted_at IS NULL GROUP BY mapname HAVING cnt >= {} ORDER BY cnt",
        physics, mode, cfg.min_map_total_participators
    ))?;
    let all_map_counts_usize: Vec<usize> = all_map_counts.iter().map(|&c| c as usize).collect();
    let category_median = median(&all_map_counts_usize);

    // Process records with logistic multiplier
    let processed = process_map_records(&records, stats, category_median, cfg);
    let multiplier = calculate_map_multiplier(stats.total_participators, category_median, cfg);
    println!("  Processed {} records (map multiplier: {:.3}, map players: {}, category median: {:.1}, k: {:.1})",
        processed.len(), multiplier, stats.total_participators, category_median, (category_median / 2.0).max(1.0));

    // Step 4: Save map scores
    conn.query_drop(&format!(
        "DELETE FROM player_map_scores WHERE mapname = '{}' AND physics = '{}' AND mode = '{}'",
        map_name.replace("'", "''"), physics, mode
    ))?;
    let saved = save_map_scores(conn, &processed, physics, mode)?;
    println!("  Saved {} map scores", saved);

    // Step 5: Get affected player mdd_ids
    let affected_mdd_ids: Vec<i32> = processed.iter()
        .map(|r| r.record.mdd_id)
        .collect::<std::collections::HashSet<i32>>()
        .into_iter()
        .collect();

    println!("  Recalculating ratings for {} affected players...", affected_mdd_ids.len());

    // Step 6: Load ALL map scores for affected players in ONE query, then process in Rust
    let mdd_id_list: Vec<String> = affected_mdd_ids.iter().map(|id| id.to_string()).collect();
    let mdd_in_clause = mdd_id_list.join(",");

    let all_scores: Vec<(i32, String, f64, String)> = conn.query_map(
        &format!(
            "SELECT pms.mdd_id, pms.mapname, pms.map_score, DATE_FORMAT(MAX(r.date_set), '%Y-%m-%d %H:%i:%s') as last_date
             FROM player_map_scores pms
             JOIN records r ON r.mdd_id = pms.mdd_id AND r.mapname = pms.mapname AND r.physics = pms.physics AND r.mode = pms.mode AND r.deleted_at IS NULL
             WHERE pms.mdd_id IN ({}) AND pms.physics = '{}' AND pms.mode = '{}'
             GROUP BY pms.mdd_id, pms.mapname, pms.map_score",
            mdd_in_clause, physics, mode
        ),
        |(mdd_id, mapname, map_score, last_date): (i32, String, f64, String)| {
            (mdd_id, mapname, map_score, last_date)
        },
    )?;

    // Group by player
    let mut player_scores: HashMap<i32, Vec<(String, f64, String)>> = HashMap::new();
    for (mdd_id, mapname, map_score, last_date) in all_scores {
        player_scores.entry(mdd_id)
            .or_insert_with(Vec::new)
            .push((mapname, map_score, last_date));
    }

    // Load player names/user_ids in one query
    let player_infos: Vec<(i32, String, Option<i32>)> = conn.query_map(
        &format!(
            "SELECT mdd_id, name, user_id FROM (
                SELECT mdd_id, name, user_id, ROW_NUMBER() OVER (PARTITION BY mdd_id ORDER BY date_set DESC) as rn
                FROM records WHERE mdd_id IN ({}) AND physics = '{}' AND mode = '{}' AND deleted_at IS NULL
            ) t WHERE rn = 1",
            mdd_in_clause, physics, mode
        ),
        |(mdd_id, name, user_id): (i32, String, Option<i32>)| {
            (mdd_id, name, user_id)
        },
    )?;

    let player_info_map: HashMap<i32, (String, Option<i32>)> = player_infos.into_iter()
        .map(|(mdd_id, name, user_id)| (mdd_id, (name, user_id)))
        .collect();

    let categories = ["overall", "rocket", "plasma", "grenade", "slick", "tele", "bfg", "strafe", "lg"];

    for &category in &categories {
        // Build upsert values for all affected players at once
        let mut upsert_values: Vec<String> = Vec::new();

        for &mdd_id in &affected_mdd_ids {
            let scores = match player_scores.get(&mdd_id) {
                Some(s) => s,
                None => continue,
            };

            let filtered: Vec<(f64, &String)> = scores.iter()
                .filter(|(mapname, _, _)| {
                    maps_map.get(&mapname.to_lowercase())
                        .map(|map| map_matches_category(map, category))
                        .unwrap_or(false)
                })
                .map(|(_, score, date)| (*score, date))
                .collect();

            if filtered.is_empty() {
                conn.query_drop(&format!(
                    "DELETE FROM player_ratings WHERE mdd_id = {} AND physics = '{}' AND mode = '{}' AND category = '{}'",
                    mdd_id, physics, mode, category
                ))?;
                continue;
            }

            let mut map_scores: Vec<f64> = filtered.iter().map(|(s, _)| *s).collect();
            let last_activity = filtered.iter().map(|(_, d)| (*d).clone()).max().unwrap();
            let record_count = map_scores.len();
            let rating = calculate_player_rating(&mut map_scores, cfg);

            let (name, user_id) = player_info_map.get(&mdd_id)
                .cloned()
                .unwrap_or(("unknown".to_string(), None));

            upsert_values.push(format!(
                "('{}', {}, {}, '{}', '{}', '{}', 0, 0, 0, {}, '{}', {}, NOW(), NOW())",
                name.replace("'", "''"),
                mdd_id,
                user_id.map_or("NULL".to_string(), |id| id.to_string()),
                physics, mode, category,
                record_count,
                last_activity,
                rating,
            ));
        }

        // Bulk upsert
        if !upsert_values.is_empty() {
            for chunk in upsert_values.chunks(100) {
                conn.query_drop(&format!(
                    "INSERT INTO player_ratings (name, mdd_id, user_id, physics, mode, category, all_players_rank, active_players_rank, category_total_participators, player_records_in_category, last_activity, player_rating, created_at, updated_at)
                     VALUES {}
                     ON DUPLICATE KEY UPDATE name=VALUES(name), user_id=VALUES(user_id), player_records_in_category=VALUES(player_records_in_category), last_activity=VALUES(last_activity), player_rating=VALUES(player_rating), updated_at=NOW()",
                    chunk.join(",")
                ))?;
            }
        }

        // Step 7: Re-rank all players for this category using bulk SQL
        let total: Option<i64> = conn.query_first(&format!(
            "SELECT COUNT(*) FROM player_ratings WHERE physics = '{}' AND mode = '{}' AND category = '{}'",
            physics, mode, category
        ))?;
        let total = total.unwrap_or(0);

        // Update category_total_participators
        conn.query_drop(&format!(
            "UPDATE player_ratings SET category_total_participators = {} WHERE physics = '{}' AND mode = '{}' AND category = '{}'",
            total, physics, mode, category
        ))?;

        // Bulk re-rank all_players_rank
        conn.query_drop("SET @rank = 0")?;
        conn.query_drop(&format!(
            "UPDATE player_ratings SET all_players_rank = (@rank := @rank + 1) WHERE physics = '{}' AND mode = '{}' AND category = '{}' ORDER BY player_rating DESC",
            physics, mode, category
        ))?;

        // Bulk re-rank active_players_rank
        let three_months_ago = chrono::Utc::now() - chrono::Duration::days(90);
        let three_months_ago_str = three_months_ago.format("%Y-%m-%d %H:%M:%S").to_string();

        // Reset active rank to 0 for inactive players
        conn.query_drop(&format!(
            "UPDATE player_ratings SET active_players_rank = 0 WHERE physics = '{}' AND mode = '{}' AND category = '{}' AND last_activity < '{}'",
            physics, mode, category, three_months_ago_str
        ))?;

        // Assign active ranks
        conn.query_drop("SET @arank = 0")?;
        conn.query_drop(&format!(
            "UPDATE player_ratings SET active_players_rank = (@arank := @arank + 1) WHERE physics = '{}' AND mode = '{}' AND category = '{}' AND last_activity >= '{}' ORDER BY player_rating DESC",
            physics, mode, category, three_months_ago_str
        ))?;
    }

    println!("  Incremental recalc complete.");

    Ok(())
}

fn main() -> Result<()> {
    let args: Vec<String> = std::env::args().collect();
    if args.len() < 3 {
        eprintln!("Usage: {} <physics> <mode> [category] [--map=mapname]", args[0]);
        std::process::exit(1);
    }

    let physics = &args[1];
    let mode = &args[2];

    // Check for --map= flag (incremental mode)
    let map_flag: Option<String> = args.iter()
        .find(|a| a.starts_with("--map="))
        .map(|a| a.trim_start_matches("--map=").to_string());

    let category = args.get(3)
        .filter(|a| !a.starts_with("--"))
        .map(|s| s.as_str())
        .unwrap_or("overall");

    let start = std::time::Instant::now();

    // Connect to MySQL
    let db_host = std::env::var("DB_HOST").unwrap_or_else(|_| "mysql".to_string());
    let db_user = std::env::var("DB_USERNAME").unwrap_or_else(|_| "sail".to_string());
    let db_pass = std::env::var("DB_PASSWORD").unwrap_or_else(|_| "password".to_string());
    let db_name = std::env::var("DB_DATABASE").unwrap_or_else(|_| "defrag-racing-project".to_string());

    let url = format!("mysql://{}:{}@{}/{}", db_user, db_pass, db_host, db_name);
    let opts = mysql::Opts::from_url(&url).expect("Invalid MySQL URL");
    let pool = Pool::new(opts)?;
    let mut conn = pool.get_conn()?;

    // Load rating config from database (rating_settings table)
    let cfg = RatingConfig::from_db(&mut conn);
    println!("Config: D={}, A={}, B={}, M={}, V={}, Q={}, MULT_L={}, MULT_N={}, RANK_N={}, RANK_V={}, min_players={}, min_time={}, max_tied_wr={}, min_records={}",
        cfg.cfg_d, cfg.cfg_a, cfg.cfg_b, cfg.cfg_m, cfg.cfg_v, cfg.cfg_q, cfg.mult_l, cfg.mult_n, cfg.rank_n, cfg.rank_v,
        cfg.min_map_total_participators, cfg.min_top1_time, cfg.max_tied_wr_players, cfg.min_total_records);

    // Load maps for category filtering
    println!("Step 0: Loading maps from database...");
    let maps: Vec<MapInfo> = conn.query_map(
        "SELECT name, weapons, functions FROM maps",
        |(name, weapons, functions)| {
            MapInfo { name, weapons, functions }
        },
    )?;
    let maps_map: HashMap<String, MapInfo> = maps.into_iter()
        .map(|m| (m.name.to_lowercase(), m))
        .collect();
    println!("  Loaded {} maps", maps_map.len());

    if let Some(map_name) = map_flag {
        // Incremental mode
        incremental_recalc(&mut conn, physics, mode, &map_name, &maps_map, &cfg)?;
    } else {
        // Full recalc mode
        println!("Calculating ratings for {} {} [{}]...", physics, mode, category);
        full_recalc(&mut conn, physics, mode, category, &maps_map, &cfg)?;
    }

    let duration = start.elapsed();
    println!("✓ Completed in {:.2}s", duration.as_secs_f64());

    let summary = serde_json::json!({
        "success": true,
        "duration_seconds": duration.as_secs_f64(),
        "physics": physics,
        "mode": mode,
        "category": category,
    });

    println!("\n{}", serde_json::to_string_pretty(&summary).unwrap());

    Ok(())
}
