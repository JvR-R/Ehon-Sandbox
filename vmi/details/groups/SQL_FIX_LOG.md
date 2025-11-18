# SQL Syntax Error Fix - November 18, 2025

## Problem

MariaDB SQL syntax error in `groups_data.php` line 108:

```
You have an error in your SQL syntax; near 'LEFT JOIN client_site_groups csg ON 1=0 ORDER BY...'
```

## Root Cause

The SQL query was being constructed in the **wrong order**:

```sql
-- INCORRECT (Invalid SQL)
SELECT ... FROM Sites JOIN Clients WHERE ... LEFT JOIN client_site_groups ...

-- CORRECT (Valid SQL)
SELECT ... FROM Sites JOIN Clients LEFT JOIN client_site_groups ... WHERE ...
```

**Problem**: The `LEFT JOIN` must come **after** the `FROM`/`JOIN` clauses but **before** the `WHERE` clause.

## What Was Happening

The original code structure:
1. Built `$baseQuery` = `"FROM Sites ... WHERE ..."`
2. Appended `LEFT JOIN` after the WHERE clause ❌
3. Created invalid SQL syntax

## The Fix

Restructured query building:

1. **Split the query** into separate parts:
   ```php
   $baseFrom = "FROM Sites cs JOIN Clients clc ...";
   $baseWhere = "WHERE (cs.client_id = ? OR ...)";
   ```

2. **Insert LEFT JOIN in correct position**:
   ```php
   $dataQuery = "SELECT ... " . $baseFrom;
   $dataQuery .= " LEFT JOIN client_site_groups csg ..."; // ✓ After FROM
   $dataQuery .= " " . $baseWhere;                         // ✓ Then WHERE
   ```

3. **Fixed parameter management**:
   - `$baseParams` - for total count query
   - `$filteredParams` - for filtered count query (base + search)
   - `$dataParams` - for data query (base + search + group + limit)

## Files Changed

- `/home/ehon/public_html/vmi/details/groups/groups_data.php`

## Changes Made

### Before (Broken):
```php
$baseQuery = "FROM Sites ... WHERE ...";
$dataQuery = "SELECT ... " . $baseQuery;
$dataQuery .= " LEFT JOIN ..."; // ❌ Wrong position!
```

### After (Fixed):
```php
$baseFrom = "FROM Sites ... JOIN Clients ...";
$baseWhere = "WHERE ...";
$baseQuery = $baseFrom . " " . $baseWhere;

$dataQuery = "SELECT ... " . $baseFrom;
$dataQuery .= " LEFT JOIN ..."; // ✓ Correct position!
$dataQuery .= " " . $baseWhere; // ✓ WHERE comes after JOIN
```

## SQL Query Structure (Correct Order)

```sql
SELECT columns
FROM table1
JOIN table2 ON ...
LEFT JOIN table3 ON ...     ← Must be here
WHERE conditions            ← Not before here
ORDER BY columns
LIMIT x OFFSET y
```

## Testing

After the fix, the query should execute successfully:
1. Initial page load - loads first 25 sites
2. Search functionality - filters sites
3. Group selection - pre-checks sites in group
4. Pagination - loads next pages

## Status

✅ **FIXED** - SQL syntax error resolved
✅ No linter errors
✅ Ready for testing

## Next Steps

1. Test the page: `/vmi/details/groups/`
2. Try selecting a group
3. Test search functionality
4. Test pagination
5. Verify group updates work

---

**Fixed By**: AI Assistant  
**Date**: November 18, 2025  
**Issue**: SQL syntax error with LEFT JOIN placement  
**Resolution**: Restructured query building to place LEFT JOIN in correct position

