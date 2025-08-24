<?php 
// Include the Decrypt class
include('AesDecryption.php');

// Get the selected file
$filePath = $_GET['file'] ?? '';

if ($filePath && file_exists($filePath)) {
    // Create an instance of AesDecryption
    $aesDecryption = new AesDecryption();

    // Decrypt the file
    $rows = $aesDecryption->decryptFile($filePath, 'secure/AES.KEY');
} else {
    $rows = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View File</title>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css">
    <!-- jQuery and DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
</head>
<body>
    <h2>File Viewer</h2>
    <table id="fileDataTable" class="display">
            <thead>
                <tr>
                <?php
                if (!empty($rows)) {
                    // Find the maximum number of data columns (excluding RID) across all rows
                    $maxDataColumns = 0;
                    foreach ($rows as $r) {
                        $dataCount = count($r) - 1; // subtract 1 for the RID
                        if ($dataCount > $maxDataColumns) {
                            $maxDataColumns = $dataCount;
                        }
                    }
                    echo '<th>No.</th>';
                    for ($colIndex = 1; $colIndex <= $maxDataColumns; $colIndex++) {
                        echo "<th>Column {$colIndex}</th>";
                    }
                }
                ?>
                </tr>
            </thead>
            <tbody>
                <?php $index = 1; // Initialize the numbering ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo $index++; ?></td>  <!-- Display numbering in the first column -->
                        <?php foreach ($row as $key => $column): ?>
                            <?php if ($key > 0): ?> <!-- Skip the first column since it’s for numbering -->
                                <td><?php echo htmlspecialchars($column); ?></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
    </table>
    <script>
    $(document).ready(function() {
        $('#fileDataTable').DataTable({
            "searching": false, // Disable search
            "paging": true,     // Enable pagination
            "columnDefs": [
                { "width": "10%", "targets": 0 }
            ]
        });
    });
    </script>
</body>
</html>
