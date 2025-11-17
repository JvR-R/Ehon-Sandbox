# Groups System - Testing Checklist

## Pre-Deployment Testing

### ✅ Database Setup
- [ ] Run `optimize_groups_db.sql` on the database
- [ ] Verify indexes were created: `SHOW INDEX FROM Sites;`
- [ ] Verify indexes on client_site_groups: `SHOW INDEX FROM client_site_groups;`
- [ ] Run `ANALYZE TABLE Sites, Clients, site_groups, client_site_groups;`

### ✅ File Permissions
- [ ] Ensure groups.php is readable (chmod 644)
- [ ] Ensure groups_data.php is readable (chmod 644)
- [ ] Ensure fetch_data.php is readable (chmod 644)

### ✅ Basic Functionality
- [ ] Page loads without errors
- [ ] No JavaScript errors in browser console (F12)
- [ ] DataTables CSS loads correctly
- [ ] DataTables JS loads correctly
- [ ] Table displays with data

## Feature Testing

### 1. Create New Group
- [ ] Enter group name
- [ ] Click "Create Group"
- [ ] Group appears in dropdown
- [ ] No errors displayed

### 2. Load Sites Table
- [ ] Sites load on page load (default view)
- [ ] Table shows correct columns: Checkbox, Site Number, Company Name, Site Name
- [ ] Pagination controls appear at bottom
- [ ] Shows correct total count

### 3. Select Group
- [ ] Click group dropdown
- [ ] Select an existing group
- [ ] Table reloads with loading spinner
- [ ] Previously selected sites are checked
- [ ] "Selected: X sites" appears

### 4. Search Functionality
- [ ] Type in search box (top-right)
- [ ] Results filter in real-time
- [ ] Search works for site numbers
- [ ] Search works for company names
- [ ] Search works for site names
- [ ] Clear search returns all results

### 5. Pagination
- [ ] Click "Next" button - goes to page 2
- [ ] Click "Previous" button - goes back to page 1
- [ ] Click page number - jumps to that page
- [ ] Change page length (10/25/50/100) - table adjusts
- [ ] Selection persists across page changes

### 6. Sorting
- [ ] Click "Site Number" header - sorts ascending
- [ ] Click again - sorts descending
- [ ] Click "Company Name" header - sorts by company
- [ ] Click "Site Name" header - sorts by site name

### 7. Selection Features
- [ ] Click individual checkbox - site is selected
- [ ] Click "Select All" checkbox - all visible sites selected
- [ ] Unclick "Select All" - all visible sites deselected
- [ ] Select sites on page 1, go to page 2, come back - selections persist
- [ ] Counter shows correct number of selected sites
- [ ] Click "Clear Selection" - all selections cleared

### 8. Update Group
- [ ] Select multiple sites
- [ ] Click "Update Group"
- [ ] Page redirects successfully
- [ ] Reload page and select same group - selections are saved
- [ ] Database has correct entries in client_site_groups table

### 9. Edge Cases
- [ ] Select group with 0 sites - table is empty, no errors
- [ ] Submit with 0 selections - shows confirmation dialog
- [ ] Submit after confirming 0 selections - clears all sites from group
- [ ] Very long site names - display correctly without breaking layout
- [ ] Special characters in site names - display correctly

## Performance Testing

### With Small Dataset (< 100 sites)
- [ ] Page loads in < 1 second
- [ ] Search is instant (< 200ms)
- [ ] Group selection is instant (< 500ms)
- [ ] No lag when selecting checkboxes

### With Medium Dataset (100-1000 sites)
- [ ] Page loads in < 2 seconds
- [ ] Search is fast (< 500ms)
- [ ] Group selection is fast (< 1 second)
- [ ] Pagination is smooth

### With Large Dataset (1000+ sites)
- [ ] Page loads in < 3 seconds
- [ ] Search works (< 1 second)
- [ ] Group selection works (< 2 seconds)
- [ ] No browser freezing
- [ ] Memory usage is reasonable (< 100 MB)

## Browser Testing

### Desktop Browsers
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

### Mobile Browsers (if applicable)
- [ ] Chrome Mobile
- [ ] Safari Mobile

## Security Testing
- [ ] SQL injection attempt in search box - no effect
- [ ] Invalid groupId parameter - handled gracefully
- [ ] Invalid companyId parameter - handled gracefully
- [ ] XSS attempt in site names - escaped properly
- [ ] Direct access to groups_data.php without POST - returns error

## Database Verification

### After Creating Group
```sql
SELECT * FROM site_groups WHERE group_name = 'TEST_GROUP_NAME';
-- Should show new group with correct client_id
```

### After Updating Group
```sql
SELECT * FROM client_site_groups WHERE group_id = [YOUR_GROUP_ID];
-- Should show all selected sites
```

### Check for Orphaned Records
```sql
-- Should return 0 rows (no orphaned group assignments)
SELECT csg.* 
FROM client_site_groups csg 
LEFT JOIN Sites s ON csg.site_no = s.site_id 
WHERE s.site_id IS NULL;
```

## Rollback Testing
- [ ] Backup current files before testing
- [ ] Verify old system still works (from backup)
- [ ] New system can coexist with old system
- [ ] Database changes are backward compatible

## Common Issues & Quick Fixes

| Issue | Check | Fix |
|-------|-------|-----|
| Table empty | Console errors? | Check groups_data.php path |
| No search | Indexes created? | Run optimize_groups_db.sql |
| Slow loading | Analyzed tables? | Run ANALYZE TABLE command |
| Selections not saving | Form submitting? | Check group_updt.php path |
| JavaScript errors | Libraries loaded? | Check CDN URLs |

## Performance Metrics to Record

Record these metrics before and after deployment:

```
Test Date: ___________
Number of Sites: ___________

Before Upgrade:
- Page Load Time: ___________ seconds
- Time to Select Group: ___________ seconds
- Browser Memory Usage: ___________ MB

After Upgrade:
- Page Load Time: ___________ seconds  (Target: < 1s)
- Time to Select Group: ___________ seconds  (Target: < 0.5s)
- Browser Memory Usage: ___________ MB  (Target: < 50 MB)
- Search Response Time: ___________ ms  (Target: < 500ms)
```

## Sign-Off

- [ ] All critical tests passed
- [ ] Performance is acceptable
- [ ] No errors in logs
- [ ] User acceptance test completed
- [ ] Documentation reviewed

**Tested By:** ___________________
**Date:** ___________________
**Approved By:** ___________________
**Date:** ___________________

## Quick Test Script

Run this in browser console (F12) after page loads:

```javascript
// Check if DataTables loaded
console.log('jQuery:', typeof $ !== 'undefined' ? '✓' : '✗');
console.log('DataTables:', typeof $.fn.DataTable !== 'undefined' ? '✓' : '✗');

// Check if table initialized
console.log('Table exists:', $('#sitesTable').length > 0 ? '✓' : '✗');
console.log('DataTable initialized:', $.fn.DataTable.isDataTable('#sitesTable') ? '✓' : '✗');

// Check AJAX endpoint
fetch('groups_data.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'draw=1&start=0&length=10&companyId=' + companyId + '&groupId=0'
})
.then(r => r.json())
.then(d => console.log('API Response:', d.recordsTotal + ' total records'))
.catch(e => console.error('API Error:', e));
```

Expected output:
```
jQuery: ✓
DataTables: ✓
Table exists: ✓
DataTable initialized: ✓
API Response: XXXX total records
```

