function selectAllCheckboxes() {
  const checkboxes = document.getElementsByName('selected_checkboxes[]');
  const selectAllButton = document.querySelector('button[onclick="selectAllCheckboxes()"]');

  checkboxes.forEach(checkbox => {
    checkbox.checked = true;
  });

  // Disable the "Select All" button after selecting all checkboxes
  selectAllButton.disabled = true;
}

document.addEventListener('DOMContentLoaded', function() {
  table_rows = document.querySelectorAll('tbody tr'),
  table_headings = document.querySelectorAll('thead th');

  // Sorting and ordering functionality
  table_headings.forEach((head, i) => {
    let sort_asc = true;
    head.onclick = () => {
      table_headings.forEach(head => head.classList.remove('active'));
      head.classList.add('active');

      document.querySelectorAll('td').forEach(td => td.classList.remove('active'));
      table_rows.forEach(row => {
        row.querySelectorAll('td')[i].classList.add('active');
      })

      head.classList.toggle('asc', sort_asc);
      sort_asc = head.classList.contains('asc') ? false : true;

      sortTable(i, sort_asc);
    }
  });

function sortTable(column, sort_asc) {
  if (9 < column){
      [...table_rows].sort((x, y) => {
          let first_row = parseInt(x.querySelectorAll('td')[column].textContent) || 0,
              second_row = parseInt(y.querySelectorAll('td')[column].textContent) || 0;
        
          return sort_asc ? (first_row - second_row) : (second_row - first_row);
        })
          .forEach(sorted_row => document.querySelector('tbody').appendChild(sorted_row));
        
  }
  if(6 <= column <= 8){
      [...table_rows].sort((a, b) => {
          let first_row = parseFloat(a.querySelectorAll('td')[column].textContent) || 0,
              second_row = parseFloat(b.querySelectorAll('td')[column].textContent) || 0;
        
          // Extract the numeric part of the percentage (remove '%' and convert to float)
          if (!isNaN(first_row)) {
            first_row = parseFloat(first_row.toFixed(2)) || 0;
          }
          if (!isNaN(second_row)) {
            second_row = parseFloat(second_row.toFixed(2)) || 0;
          }
        
          return sort_asc ? (first_row - second_row) : (second_row - first_row);
        })
          .forEach(sorted_row => document.querySelector('tbody').appendChild(sorted_row));
        
  }
  if(column<6 || column == 9){
      [...table_rows].sort((a, b) => {
          let first_row = a.querySelectorAll('td')[column].textContent.toLowerCase(),
              second_row = b.querySelectorAll('td')[column].textContent.toLowerCase();

          return sort_asc ? (first_row < second_row ? 1 : -1) : (first_row < second_row ? -1 : 1);
      })
          .map(sorted_row => document.querySelector('tbody').appendChild(sorted_row));
  }
}
const exportForm = document.getElementById('exportForm');
    if (exportForm) {
        const exportButton = exportForm.querySelector('button[name="export"]');
        if (exportButton) {
            exportButton.addEventListener('click', function() {
                exportForm.submit(); // Submit the form
            });
        }
    }

});
