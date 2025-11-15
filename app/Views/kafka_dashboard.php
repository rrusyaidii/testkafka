<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kafka Messages Dashboard</title>
    <!-- Import jQuery library -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- CSS Styles for a prettier look -->
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f9;
            margin: 20px;
            color: #333;
        }

        h2 {
            color: #0056b3;
            border-bottom: 2px solid #0056b3;
            padding-bottom: 10px;
        }

        #kafka-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            background-color: #fff;
        }

        #kafka-table th, #kafka-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            word-wrap: break-word; /* Prevents long strings from breaking layout */
        }

        #kafka-table th {
            background-color: #007bff;
            color: white;
            font-weight: 600;
        }

        #kafka-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        #kafka-table tbody tr:hover {
            background-color: #e9ecef;
            cursor: pointer;
        }

        /* Specific styling for raw JSON column content */
        #kafka-table td:nth-child(4) {
            font-family: monospace;
            font-size: 0.8em;
            color: #555;
            max-width: 300px; /* Constrains the width of the JSON */
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap; /* Keeps JSON on a single line with ellipsis */
        }
    </style>
</head>
<body>
    <h2>Kafka Messages Dashboard</h2>

    <table id="kafka-table">
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

        <tbody>
            <!-- Data rows will be inserted here by JavaScript -->
        </tbody>
    </table>

    <script>
        function loadData() {
            // Fetch data from the API endpoint
            $.get('/api/kafka/messages', function(data) {
                // Sort the data array by the 'id' field in ascending order
                data.sort((a, b) => a.id - b.id);

                let rows = '';
                data.forEach(row => {
                    // Use a function to safely display JSON in the table
                    const displayJson = row.raw_json ? JSON.stringify(row.raw_json) : 'N/A';
                    rows += `
                        <tr>
                            <td>${row.id}</td>
                            <td>${row.order_id}</td>
                            <td>${row.user_id}</td>
                            <td>${row.amount}</td>
                            <td title="${displayJson}">${displayJson}</td>
                            <td>${row.created_at}</td>
                        </tr>
                    `;
                });
                // Update the table body with the new rows
                $('#kafka-table tbody').html(rows);
            }).fail(function() {
                console.error("Failed to fetch Kafka messages.");
                $('#kafka-table tbody').html('<tr><td colspan="5">Error loading data. Check console for details.</td></tr>');
            });
        }

        // Auto refresh every 3 seconds (3000ms)
        setInterval(loadData, 3000);
        
        // Initial load of data when the page loads
        loadData();
    </script>
</body>
</html>
