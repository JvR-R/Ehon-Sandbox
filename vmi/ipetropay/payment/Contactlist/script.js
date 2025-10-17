document.addEventListener('DOMContentLoaded', function() {
  const search = document.querySelector('.input-group input');
  const table_rows = document.querySelectorAll('tbody tr');
  const table_headings = document.querySelectorAll('thead th');

  // 1. Searching for specific data of HTML table
  search.addEventListener('input', searchTable);

  function searchTable() {
    table_rows.forEach((row, i) => {
      let table_data = row.textContent.toLowerCase();
      let search_data = search.value.toLowerCase();

      row.classList.toggle('hide', table_data.indexOf(search_data) < 0);
      row.style.setProperty('--delay', i / 25 + 's');
    });

    document.querySelectorAll('tbody tr:not(.hide)').forEach((visible_row, i) => {
      visible_row.style.backgroundColor = i % 2 == 0 ? 'transparent' : '#0000000b';
    });
  }

  // 2. Sorting | Ordering data of HTML table

  table_headings.forEach((head, i) => {
    let sort_asc = true;
    head.onclick = () => {
      table_headings.forEach((head) => head.classList.remove('active'));
      head.classList.add('active');

      document.querySelectorAll('td').forEach((td) => td.classList.remove('active'));
      table_rows.forEach((row) => {
        row.querySelectorAll('td')[i].classList.add('active');
      });

      head.classList.toggle('asc', sort_asc);
      sort_asc = head.classList.contains('asc') ? false : true;

      sortTable(i, sort_asc);
    };
  });

  function sortTable(column, sort_asc) {
    if (7 <= column <= 9) {
      [...table_rows]
        .sort((x, y) => {
          let first_row = parseInt(x.querySelectorAll('td')[column].textContent) || 0;
          let second_row = parseInt(y.querySelectorAll('td')[column].textContent) || 0;

          return sort_asc ? first_row - second_row : second_row - first_row;
        })
        .forEach((sorted_row) => document.querySelector('tbody').appendChild(sorted_row));
    }
    if (column == 10) {
      [...table_rows]
        .sort((a, b) => {
          let first_cell = a.querySelectorAll('td')[column].textContent.trim();
          let second_cell = b.querySelectorAll('td')[column].textContent.trim();

          // Handling empty cells
          if (first_cell === '') {
            return 1; // Move first_cell to a lower index
          } else if (second_cell === '') {
            return -1; // Move second_cell to a lower index
          }

          let first_row = parseFloat(first_cell) || 0;
          let second_row = parseFloat(second_cell) || 0;

          // Extract the numeric part of the percentage (remove '%' and convert to float)
          if (!isNaN(first_row)) {
            first_row = parseFloat(first_row.toFixed(2)) || 0;
          }
          if (!isNaN(second_row)) {
            second_row = parseFloat(second_row.toFixed(2)) || 0;
          }

          return sort_asc ? first_row - second_row : second_row - first_row;
        })
        .forEach((sorted_row) => document.querySelector('tbody').appendChild(sorted_row));
    }

    if (column < 7) {
      [...table_rows]
        .sort((a, b) => {
          let first_row = a.querySelectorAll('td')[column].textContent.toLowerCase();
          let second_row = b.querySelectorAll('td')[column].textContent.toLowerCase();

          return sort_asc ? (first_row < second_row ? 1 : -1) : first_row < second_row ? -1 : 1;
        })
        .map((sorted_row) => document.querySelector('tbody').appendChild(sorted_row));
    }
  }

  // 5. Converting HTML table to CSV File

  const csv_btn = document.querySelector('#toCSV');

  const toCSV = function(table) {
    const t_heads = table.querySelectorAll('th');
    const tbody_rows = table.querySelectorAll('tbody tr');

    const headings = [...t_heads]
      .map((head) => {
        let actual_head = head.textContent.trim().split(' ');
        return actual_head.splice(0, actual_head.length - 1).join(' ').toLowerCase();
      })
      .join(',') + ',';

    const table_data = [...tbody_rows]
      .map((row) => {
        const cells = row.querySelectorAll('td');
        let data_without_img = [...cells]
          .map((cell) => cell.textContent.replace(/,/g, '.').trim())
          .join(',');
        data_without_img = data_without_img.replace(/❌|🟡|✅/g, '');
        return data_without_img;
      })
      .join('\n');

    return headings + '\n' + table_data;
  };

  csv_btn.onclick = () => {
    const csv = toCSV(customers_table);
    downloadFile(csv, 'csv', 'Vendor Managed Inventory');
  };

  const downloadFile = function(data, fileType, fileName = '') {
    const a = document.createElement('a');
    a.download = fileName;
    const mime_types = {
      csv: 'text/csv',
    };
    a.href = `
        data:${mime_types[fileType]};charset=utf-8,${encodeURIComponent(data)}
    `;
    document.body.appendChild(a);
    a.click();
    a.remove();
  };

  // Function to display the toast notification with a pie chart
  function showToast(data, cell) {
    var toast = document.getElementById('toastNotification');
    toast.innerHTML = `
      <canvas id="chart"></canvas>
      <button class="close-btn" onclick="hideToast()">Close</button>
    `;

    var rect = cell.getBoundingClientRect();
    var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    var cellTop = rect.top + scrollTop;

    toast.style.top = cellTop + 'px';
    toast.style.left = rect.left + 'px';

    // Adjust the toast position if it exceeds the window width
    var chartWidth = 100; // Adjust this value as needed
    var windowWidth = window.innerWidth;
    if (rect.left + chartWidth > windowWidth) {
      toast.style.left = windowWidth - chartWidth + 'px';
    }

    toast.classList.add('show-toast');

    // Render the pie chart
    var ctx = document.getElementById('chart').getContext('2d');
    new Chart(ctx, {
      type: 'pie',
      data: {
        labels: ['Current Volume', 'Ullage'],
        datasets: [
          {
            data: data,
            backgroundColor: ['#00cc00', '#ffcc00', '#ff3300'],
            borderWidth: 0,
          },
        ],
      },
      options: {
        legend: {
          display: true,
        },
      },
    })
  }

  // Add click event listener to the table cells in the "current_percent" column
  var cells = document.querySelectorAll('#customers_table tbody tr td:nth-child(11)');
  cells.forEach(function(cell) {
    cell.addEventListener('click', function() {
      var percent = parseFloat(cell.textContent);
      var data = [percent, 100 - percent, 0];
      showToast(data, cell);
    });
  })
  // Add click event listener to the table cells in the "tank_no" column
var cells = document.querySelectorAll('#customers_table tbody tr td:nth-child(6)');
cells.forEach(function(cell) {
  cell.addEventListener('click', function() {
    var tankNo = cell.textContent;
    var siteNumber = cell.parentElement.querySelector('td:nth-child(4)').textContent;
    var companyname = cell.parentElement.querySelector('td:nth-child(1)').textContent;

    
    // Perform the desired action when a tank_no cell is clicked
    // For example, open a new window with the tank details
    window.open('/ehon/clients/info/?Companyname=' + companyname + '&Sitename=' + siteNumber + '&Tanknumber=' + tankNo, "", "height=720,width=960,scrollbars=no");
  });
})
});
// Function to hide the toast notification
function hideToast() {
  var toast = document.getElementById('toastNotification');
  toast.classList.remove('show-toast');
}
