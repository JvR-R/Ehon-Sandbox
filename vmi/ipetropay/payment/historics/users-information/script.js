document.addEventListener('DOMContentLoaded', function() {
    const tableRows = document.querySelectorAll('tbody tr');
    const tableHeadings = document.querySelectorAll('thead th');

    tableHeadings.forEach((heading, index) => {
        let isAscending = true;

        heading.addEventListener('click', () => {
            tableHeadings.forEach(th => th.classList.remove('active', 'asc', 'desc'));
            heading.classList.add('active', isAscending ? 'asc' : 'desc');

            sortTableByColumn(index, isAscending);
            isAscending = !isAscending;
        });
    });

    function parseNumeric(value) {
        return parseFloat(value.replace(/,/g, '').replace(/[^\d.-]/g, '')) || 0;
    }

    function sortTableByColumn(column, ascending) {
        const sortedRows = Array.from(tableRows).sort((rowA, rowB) => {
            const cellA = rowA.querySelectorAll('td')[column].textContent.trim();
            const cellB = rowB.querySelectorAll('td')[column].textContent.trim();

            let comparison = 0;

            if (column >= 4 && column <= 6) {  // Corrected column indexes based on your current table structure
                comparison = parseNumeric(cellA) - parseNumeric(cellB);
            } else if (!isNaN(Date.parse(cellA)) && !isNaN(Date.parse(cellB))) {
                comparison = new Date(cellA) - new Date(cellB);
            } else {
                comparison = cellA.localeCompare(cellB);
            }

            return ascending ? comparison : -comparison;
        });

        const tbody = document.querySelector('tbody');
        tbody.innerHTML = '';
        sortedRows.forEach(row => tbody.appendChild(row));
    }
});
