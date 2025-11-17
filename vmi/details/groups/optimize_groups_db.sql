-- ============================================
-- SQL Optimization Script for Groups System
-- ============================================
-- Run this script to add indexes for better performance
-- These indexes will significantly speed up queries when dealing with large datasets

-- 1. Sites table indexes
-- Index on client_id for faster joins and WHERE clauses
CREATE INDEX IF NOT EXISTS idx_sites_client_id ON Sites(client_id);

-- Index on uid for the special case where companyId = 15100
CREATE INDEX IF NOT EXISTS idx_sites_uid ON Sites(uid);

-- Composite index for site searches
CREATE INDEX IF NOT EXISTS idx_sites_search ON Sites(site_id, site_name);

-- 2. Clients table indexes
-- Index on reseller_id and Dist_id for multi-level client queries
CREATE INDEX IF NOT EXISTS idx_clients_reseller ON Clients(reseller_id);
CREATE INDEX IF NOT EXISTS idx_clients_dist ON Clients(Dist_id);
CREATE INDEX IF NOT EXISTS idx_clients_name ON Clients(client_name);

-- 3. site_groups table indexes
-- Primary lookup by client
CREATE INDEX IF NOT EXISTS idx_site_groups_client ON site_groups(client_id);

-- Composite index for group selection dropdown
CREATE INDEX IF NOT EXISTS idx_site_groups_lookup ON site_groups(client_id, group_id);

-- 4. client_site_groups table indexes (junction table)
-- Primary composite index for group membership queries
CREATE INDEX IF NOT EXISTS idx_csg_group_client ON client_site_groups(group_id, client_id);

-- Index for site-based lookups
CREATE INDEX IF NOT EXISTS idx_csg_site ON client_site_groups(site_no);

-- Composite index for efficient joins in DataTables query
CREATE INDEX IF NOT EXISTS idx_csg_full_lookup ON client_site_groups(site_no, group_id, client_id);

-- 5. console table indexes (for device_type filtering)
CREATE INDEX IF NOT EXISTS idx_console_uid_device ON console(uid, device_type);

-- ============================================
-- Optional: Add these if tables are missing primary keys
-- ============================================

-- Ensure site_groups has proper primary key
-- ALTER TABLE site_groups ADD PRIMARY KEY (group_id) IF NOT EXISTS;

-- Ensure client_site_groups has proper composite key
-- ALTER TABLE client_site_groups ADD PRIMARY KEY (group_id, site_no) IF NOT EXISTS;

-- ============================================
-- Performance Analysis Queries
-- ============================================
-- Run these queries to check if indexes are being used

-- Check current indexes on Sites table
-- SHOW INDEX FROM Sites;

-- Check current indexes on site_groups table
-- SHOW INDEX FROM site_groups;

-- Check current indexes on client_site_groups table
-- SHOW INDEX FROM client_site_groups;

-- Analyze query performance (run EXPLAIN before and after adding indexes)
-- EXPLAIN SELECT cs.site_id, cs.site_name, clc.client_name
-- FROM Sites cs 
-- JOIN Clients clc ON cs.client_id = clc.client_id 
-- WHERE cs.client_id = 1234
-- LIMIT 25;

-- ============================================
-- Maintenance Recommendations
-- ============================================

-- 1. Run ANALYZE TABLE periodically to update table statistics
-- ANALYZE TABLE Sites, Clients, site_groups, client_site_groups, console;

-- 2. For very large tables (1M+ rows), consider partitioning:
--    - Partition Sites by client_id ranges
--    - Partition client_site_groups by group_id ranges

-- 3. Monitor slow queries:
-- SET GLOBAL slow_query_log = 'ON';
-- SET GLOBAL long_query_time = 1; -- Log queries taking > 1 second

-- 4. Check table health:
-- CHECK TABLE Sites, Clients, site_groups, client_site_groups;

-- 5. Optimize tables if needed (run during low-traffic periods):
-- OPTIMIZE TABLE Sites, Clients, site_groups, client_site_groups;

