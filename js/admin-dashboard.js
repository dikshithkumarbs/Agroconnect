document.addEventListener('DOMContentLoaded', function() {
    // View toggle functionality
    const separateViewBtn = document.getElementById('separateViewBtn');
    const allUsersViewBtn = document.getElementById('allUsersViewBtn');
    const separateView = document.getElementById('separateView');
    const allUsersView = document.getElementById('allUsersView');

    separateViewBtn.addEventListener('click', function() {
        separateViewBtn.classList.add('active');
        allUsersViewBtn.classList.remove('active');
        separateView.style.display = 'block';
        allUsersView.style.display = 'none';
    });

    allUsersViewBtn.addEventListener('click', function() {
        allUsersViewBtn.classList.add('active');
        separateViewBtn.classList.remove('active');
        allUsersView.style.display = 'block';
        separateView.style.display = 'none';
    });

    // Sorting functionality
    const sortBy = document.getElementById('sortBy');
    const allUsersTable = document.getElementById('allUsersTable');

    sortBy.addEventListener('change', function() {
        const sortValue = this.value;
        const tbody = allUsersTable.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));

        rows.sort((a, b) => {
            let aValue, bValue;

            switch(sortValue) {
                case 'name':
                    aValue = a.cells[0].textContent.toLowerCase();
                    bValue = b.cells[0].textContent.toLowerCase();
                    return aValue.localeCompare(bValue);
                case 'role':
                    aValue = a.getAttribute('data-role');
                    bValue = b.getAttribute('data-role');
                    return aValue.localeCompare(bValue);
                case 'created_at':
                    aValue = new Date(a.getAttribute('data-joined'));
                    bValue = new Date(b.getAttribute('data-joined'));
                    return bValue - aValue; // Newest first
                default:
                    return 0;
            }
        });

        // Re-append sorted rows
        rows.forEach(row => tbody.appendChild(row));
    });

    // Filtering functionality
    const filterRole = document.getElementById('filterRole');
    const filterDateFrom = document.getElementById('filterDateFrom');
    const filterDateTo = document.getElementById('filterDateTo');

    function applyFilters() {
        const roleFilter = filterRole.value;
        const dateFrom = filterDateFrom.value ? new Date(filterDateFrom.value) : null;
        const dateTo = filterDateTo.value ? new Date(filterDateTo.value) : null;

        const rows = allUsersTable.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const role = row.getAttribute('data-role');
            const joinDate = new Date(row.getAttribute('data-joined'));

            let showRow = true;

            // Role filter
            if (roleFilter && role !== roleFilter) {
                showRow = false;
            }

            // Date filter
            if (dateFrom && joinDate < dateFrom) {
                showRow = false;
            }
            if (dateTo && joinDate > dateTo) {
                showRow = false;
            }

            row.style.display = showRow ? '' : 'none';
        });
    }

    filterRole.addEventListener('change', applyFilters);
    filterDateFrom.addEventListener('change', applyFilters);
    filterDateTo.addEventListener('change', applyFilters);
});

// Placeholder function for editing users (to be implemented)
function editUser(userId) {
    alert('Edit user functionality to be implemented. User ID: ' + userId);
}

// Confirm delete function
function confirmDelete(message) {
    return confirm(message);
}
