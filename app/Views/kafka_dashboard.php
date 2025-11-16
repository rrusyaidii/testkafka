<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Kafka Messages Dashboard</title>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        :root {
            --bg: #f4f4f9;
            --card: #ffffff;
            --text: #222;
            --muted: #666;
            --accent: #007bff;
            --table-border: #e6e6e9;
            --row-hover: #e9ecef;
            --mono: #444;
        }

        [data-theme="dark"] {
            --bg: #0f1720;
            --card: #0b1220;
            --text: #e6eef8;
            --muted: #9fb1c9;
            --accent: #4f9cff;
            --table-border: #16202b;
            --row-hover: #12202a;
            --mono: #cbd6e6;
        }

        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 20px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        header {
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        h2 {
            margin: 0;
            color: var(--accent);
            font-size: 20px;
        }

        .controls {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .controls .control {
            background: var(--card);
            border: 1px solid var(--table-border);
            padding: 8px;
            border-radius: 8px;
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .controls input[type="number"],
        .controls select {
            width: 80px;
            padding: 6px 8px;
            border-radius: 6px;
            border: 1px solid var(--table-border);
            background: transparent;
            color: var(--text);
        }

        .controls label {
            font-size: 13px;
            color: var(--muted);
        }

        #themeToggle {
            padding: 6px 10px;
            background: var(--card);
            border: 1px solid var(--table-border);
            border-radius: 8px;
            cursor: pointer;
            color: var(--text);
        }

        .card {
            background: var(--card);
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(2, 6, 23, 0.06);
            padding: 12px;
        }

        #kafka-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        #kafka-table th,
        #kafka-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--table-border);
            text-align: left;
            word-break: break-word;
            vertical-align: top;
        }

        #kafka-table th {
            background: var(--accent);
            color: #fff;
            font-weight: 600;
            position: sticky;
            top: 0;
        }

        #kafka-table tbody tr:nth-child(even) {
            background: rgba(0, 0, 0, 0.02);
        }

        [data-theme="dark"] #kafka-table tbody tr:nth-child(even) {
            background: transparent;
        }

        #kafka-table tbody tr:hover {
            background: var(--row-hover);
            cursor: pointer;
        }

        #kafka-table td.raw {
            font-family: monospace;
            font-size: 0.82em;
            color: var(--mono);
            max-width: 360px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pagination {
            margin-top: 12px;
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: center;
        }

        .pagination button {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid var(--table-border);
            background: var(--card);
            color: var(--text);
            cursor: pointer;
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .page-info {
            color: var(--muted);
            font-size: 14px;
        }

        @media (max-width:720px) {
            .controls {
                flex-wrap: wrap;
                gap: 6px;
            }

            .controls .control {
                padding: 6px;
            }

            #kafka-table td.raw {
                max-width: 200px;
            }
        }
    </style>
</head>

<body>

    <header>
        <div style="display:flex;gap:12px;align-items:center">
            <h2>Kafka Messages Dashboard</h2>
            <div class="controls">
                <div class="control">
                    <label for="pageInput">Page</label>
                    <input id="pageInput" type="number" min="1" step="1" value="1" />
                </div>

                <div class="control">
                    <label for="pageSizeSelect">Rows</label>
                    <select id="pageSizeSelect">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:8px;align-items:center">
            <button id="themeToggle" title="Toggle dark mode">Toggle Dark</button>
        </div>
    </header>

    <div class="card">
        <table id="kafka-table" aria-live="polite">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Order ID</th>
                    <th>User ID</th>
                    <th>Amount</th>
                    <th>Raw JSON</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>

        <div class="pagination">
            <button id="prevPage">Prev</button>
            <span class="page-info" id="pageInfo">Page 1 of 1</span>
            <button id="nextPage">Next</button>
        </div>
    </div>

    <script>
        // state
        let allData = [];
        let currentPage = 1;
        let pageSize = 10;

        // elements
        const $pageInput = $('#pageInput');
        const $pageSizeSelect = $('#pageSizeSelect');
        const $pageInfo = $('#pageInfo');

        // theme
        const THEME_KEY = 'kafka_dashboard_theme';
        function applyTheme(theme) {
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
                $('#themeToggle').text('Light');
            } else {
                document.documentElement.removeAttribute('data-theme');
                $('#themeToggle').text('Dark');
            }
            localStorage.setItem(THEME_KEY, theme);
        }
        // init theme from storage
        const savedTheme = localStorage.getItem(THEME_KEY) || 'light';
        applyTheme(savedTheme);

        $('#themeToggle').on('click', () => {
            const now = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(now);
        });

        function totalPages() {
            return Math.max(1, Math.ceil(allData.length / pageSize));
        }

        function renderTable() {
            const total = totalPages();
            if (currentPage > total) currentPage = total;
            if (currentPage < 1) currentPage = 1;

            const start = (currentPage - 1) * pageSize;
            const end = start + pageSize;
            const pageData = allData.slice(start, end);

            let rows = '';
            pageData.forEach(row => {
                const displayJson = row.raw_json ? JSON.stringify(row.raw_json) : 'N/A';
                // escape quotes in title attribute
                const titleSafe = (displayJson + '').replace(/"/g, '&quot;');
                rows += `
                <tr>
                    <td>${row.id ?? ''}</td>
                    <td>${row.order_id ?? ''}</td>
                    <td>${row.user_id ?? ''}</td>
                    <td>${row.amount ?? ''}</td>
                    <td class="raw" title="${titleSafe}">${displayJson}</td>
                    <td>${row.created_at ?? ''}</td>
                </tr>
            `;
            });

            $('#kafka-table tbody').html(rows);

            $pageInfo.text(`Page ${currentPage} of ${total} · ${allData.length} rows`);
            $pageInput.val(currentPage);
            $('#prevPage').prop('disabled', currentPage === 1);
            $('#nextPage').prop('disabled', currentPage === total);
        }

        function loadData() {
            // request server-side page / per_page if you later implement server pagination
            $.get('/api/kafka/messages', function (data) {
                // if server returns wrapper {data:..., total:...} handle it
                if (data && data.data && Array.isArray(data.data)) {
                    // server-side pagination mode
                    allData = data.data;
                } else if (Array.isArray(data)) {
                    allData = data;
                } else {
                    allData = [];
                }

                // sort ascending by id (if you prefer newest first swap)
                allData.sort((a, b) => (a.id ?? 0) - (b.id ?? 0));

                // adjust current page if needed
                if (currentPage > totalPages()) currentPage = totalPages();
                renderTable();
            }).fail(function () {
                console.error('Failed to fetch data from /api/kafka/messages');
            });
        }

        // controls
        $('#prevPage').on('click', () => {
            if (currentPage > 1) { currentPage--; renderTable(); }
        });

        $('#nextPage').on('click', () => {
            if (currentPage < totalPages()) { currentPage++; renderTable(); }
        });

        $pageInput.on('change', function () {
            let val = parseInt($(this).val()) || 1;
            if (val < 1) val = 1;
            if (val > totalPages()) val = totalPages();
            currentPage = val;
            renderTable();
        });

        $pageSizeSelect.on('change', function () {
            pageSize = parseInt($(this).val()) || 10;
            // reset to first page to avoid out-of-range
            currentPage = 1;
            renderTable();
        });

        // auto-refresh (keeps current page/index)
        setInterval(loadData, 3000);
        // initial load
        loadData();
    </script>

</body>

</html>