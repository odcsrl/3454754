<?php
session_start();

require 'includes/db.php';

$fontLink = "";
$fonts = [];

if (file_exists('includes/google_fonts.json')) {
    $fontsJson = file_get_contents('includes/google_fonts.json');
    $fonts = json_decode($fontsJson, true);
}

$fontFamily = $_SESSION['selectedFont'] ?? "";
if ($fontFamily) {
    $fontLink = '<link href="https://fonts.googleapis.com/css?family=' . urlencode($fontFamily) . '" rel="stylesheet">';
}

if (!isset($_SESSION["user_id"])) {
    header("location: login.php");
    exit;
}

$project_id = $_GET['id'] ?? null;

if (!$project_id) {
    header("location: dashboard.php");
    exit;
}

$query = "SELECT * FROM projects WHERE id = :project_id AND user_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $_SESSION["user_id"], PDO::PARAM_INT);
$stmt->execute();

$project = $stmt->fetch();

if (!$project) {
    header("location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="app.css" rel="stylesheet">
    <?= $fontLink ?>
</head>
<body>
<div class="container mt-5 main-container">
    <h1 class="mb-5">Project: <?= htmlspecialchars($project['name']) ?></h1>

    <?php
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
        unset($_SESSION['error']);  // Clear the error after displaying
    }
    ?>

    <!-- Upload and Puzzle Size Section -->
    <div class="mb-5">
        <form action="upload.php" method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="fileUpload" class="form-label">Upload Words (.txt only)</label>
                <input type="file" class="form-control" id="fileUpload" name="fileUpload" accept=".txt" required>
            </div>
            <div class="mb-3">
                <label for="sizeAcross" class="form-label">Number of Letters Across</label>
                <input type="number" min="1" max="40" class="form-control" id="sizeAcross" name="sizeAcross" required>
            </div>
            <div class="mb-3">
                <label for="sizeDown" class="form-label">Number of Letters Down</label>
                <input type="number" min="1" max="40" class="form-control" id="sizeDown" name="sizeDown" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Word Placement Options</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="option" id="option1" value="1" required>
                    <label class="form-check-label" for="option1">Use each letter only once.</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="option" id="option2" value="2" required>
                    <label class="form-check-label" for="option2">Share letters occasionally.</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="option" id="option3" value="3" required>
                    <label class="form-check-label" for="option3">Share letters as much as possible.</label>
                </div>
                <div class="mb-3">
    <label class="form-label">Case Options</label>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="case_option" id="caseUppercase" value="uppercase" required>
        <label class="form-check-label" for="caseUppercase">All Uppercase Letters</label>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="case_option" id="caseMixed" value="mixed" required>
        <label class="form-check-label" for="caseMixed">Mixed Uppercase and Lowercase Letters</label>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="case_option" id="caseLowercase" value="lowercase" required>
        <label class="form-check-label" for="caseLowercase">All Lowercase Letters</label>
    </div>
            <!-- Font Selection Dropdown -->
        <div class="mb-3">
            <label for="fontFamily" class="form-label">Select Font</label>
            <select class="form-control" id="fontFamily" name="fontFamily">
                <option value="" selected>Default</option>
                <?php
                foreach ($fonts['items'] as $font) {
                    $selected = $font['family'] === $fontFamily ? "selected" : ""; // Check if the font is selected
                    echo '<option value="' . htmlspecialchars($font['family']) . '" ' . $selected . '>' . htmlspecialchars($font['family']) . '</option>';
                }
                ?>
            </select>
        </div>
</div>
            </div>
            <input type="hidden" name="project_id" value="<?= htmlspecialchars($project_id) ?>">
            <button type="submit" class="btn btn-primary" id="generatePuzzleBtn" disabled>Generate Puzzle</button>
            <button type="submit" class="btn btn-warning" id="regeneratePuzzleBtn" name="regenerate">Regenerate Puzzle</button>

        </form>
    </div>

    <!-- Puzzle Display Section -->
    <div class="mb-5">
        <h2 class="mb-4">Puzzle Preview:</h2>
        <div class="bordered-div">
            <?php
            if (isset($_SESSION['puzzle'])) {
                $puzzle = $_SESSION['puzzle'];
                $tableWidth = 40 * count($puzzle);
                echo '<table class="puzzle-table" style="width:'.$tableWidth.'px; height:'.$tableWidth.'px;">';
                foreach ($puzzle as $row) {
                    echo '<tr>';
                    foreach ($row as $cell) {
                        echo '<td>' . $cell . '</td>';
                    }
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo "No puzzle generated yet!";
            }
            
            ?>
        </div>
        <h2 class="mb-4 mt-5">Solution Preview:</h2>
    <div class="bordered-div">
        <?php
        if (isset($_SESSION['solution'])) {
            $solution = $_SESSION['solution'];
            $tableWidth = 40 * count($solution);
            echo '<table class="puzzle-table" style="width:'.$tableWidth.'px; height:'.$tableWidth.'px;">';
            foreach ($solution as $row) {
                echo '<tr>';
                foreach ($row as $cell) {
                    echo '<td>' . $cell . '</td>';
                }
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo "No solution generated yet!";
        }
        ?>
    </div>
    </div>


    <!-- Image Scale -->
<div class="slider-container">
    <label for="imageScale">Image Quality (Scale 1 to 10):</label>
    <input type="range" id="imageScale" name="imageScale" min="1" max="10" value="1" class="slider">
    <span id="scaleValue">1</span>
</div>



    <!-- Download Buttons -->
    <div class="mb-5 d-flex flex-wrap gap-3">
        <h2 class="mb-3 w-100">Download:</h2>
        <button id="downloadPuzzleAsPNG" class="btn btn-success">Download Puzzle as PNG</button>
        <button id="downloadPuzzleAsJPG" class="btn btn-success">Download Puzzle as JPG</button>
        <button id="downloadPuzzleAsSVG" class="btn btn-success">Download as SVG</button>

        <a href="download_puzzle.php?project_id=<?= htmlspecialchars($project_id) ?>&format=png" class="btn btn-success">PNG</a>
        <a href="download_puzzle.php?project_id=<?= htmlspecialchars($project_id) ?>&format=pdf" class="btn btn-success">PDF</a>
        <a href="download_puzzle.php?project_id=<?= htmlspecialchars($project_id) ?>&format=svg" class="btn btn-success">SVG</a>
        <?php
        if ($_SESSION['user_type'] === 'Professional') {
            echo '<a href="download_puzzle.php?project_id=' . htmlspecialchars($project_id) . '&format=pptx" class="btn btn-success">PPTX</a>';
        }
        ?>
        <a href="download_solution.php?project_id=<?= htmlspecialchars($project_id) ?>" class="btn btn-danger">Solution</a>
    </div>

    <!-- Back to dashboard -->
    <div>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
</div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileUpload = document.getElementById('fileUpload');
            const sizeAcross = document.getElementById('sizeAcross');
            const sizeDown = document.getElementById('sizeDown');
            const generatePuzzleBtn = document.getElementById('generatePuzzleBtn');
            const options = document.getElementsByName('option');

            function checkInputs() {
                const optionSelected = [...options].some(option => option.checked);
                if (fileUpload.files.length > 0 && sizeAcross.value && sizeDown.value && optionSelected) {
                    generatePuzzleBtn.disabled = false;
                } else {
                    generatePuzzleBtn.disabled = true;
                }
            }

            function regeneratePuzzle() {
            const fileUpload = document.getElementById('fileUpload');
            if (fileUpload.files.length > 0) {
                fileUpload.form.submit();
            } else {
                alert('Please upload a file first!');
            }
        }

            fileUpload.addEventListener('change', checkInputs);
            sizeAcross.addEventListener('input', checkInputs);
            sizeDown.addEventListener('input', checkInputs);
            options.forEach(option => option.addEventListener('change', checkInputs));
        });
        const scaleSlider = document.getElementById('imageScale');
const scaleValueDisplay = document.getElementById('scaleValue');

scaleSlider.addEventListener('input', function() {
    scaleValueDisplay.textContent = scaleSlider.value;
});

    </script>

    <!-- html2canvas library -->
<script src="libs/html2canvas.js"></script>

<!-- JavaScript to capture and download the puzzle -->
<script>
document.getElementById('downloadPuzzleAsPNG').addEventListener('click', function() {
    const puzzlePreview = document.querySelector('.puzzle-table');
    const scale = parseInt(document.getElementById('imageScale').value);

    html2canvas(puzzlePreview, {
        scale: scale,
        useCORS: true,
        logging: true
    }).then(canvas => {
        let link = document.createElement('a');
        link.download = 'puzzle.png';
        link.href = canvas.toDataURL();
        link.click();
    });
});
</script>
<script>
function convertTableToSVG(table) {
    // Dimensions
    const svgWidth = table.offsetWidth;
    const svgHeight = table.offsetHeight;

    // Font URL from PHP variable
    const fontURL = "https://fonts.googleapis.com/css?family=" + encodeURIComponent("<?= $fontFamily ?>");
    const svgFontStyle = `<defs><style type="text/css">@import url('${fontURL}');</style></defs>`;

    let svgContent = `<svg width="${svgWidth}" height="${svgHeight}" xmlns="http://www.w3.org/2000/svg">${svgFontStyle}`;

    // Iterate over all rows of the table
    Array.from(table.rows).forEach((row, rowIndex) => {
        Array.from(row.cells).forEach((cell, cellIndex) => {
            const x = cellIndex * cell.offsetWidth;
            const y = rowIndex * cell.offsetHeight;
            const width = cell.offsetWidth;
            const height = cell.offsetHeight;
            const textX = x + (width / 2);  // center text
            const textY = y + (height / 2) + 5;  // center text, +5 is an approximation for vertical alignment

            svgContent += `
                <rect x="${x}" y="${y}" width="${width}" height="${height}" fill="none" stroke="black" stroke-width="1"></rect>
                <text x="${textX}" y="${textY}" font-family="<?= $fontFamily ?>" font-size="16" text-anchor="middle" dominant-baseline="middle">${cell.textContent}</text>
            `;
        });
    });

    svgContent += `</svg>`;

    return svgContent;
}
</script>
<script>
document.getElementById('downloadPuzzleAsSVG').addEventListener('click', function() {
    const puzzleTable = document.querySelector('.puzzle-table');
    const svgData = convertTableToSVG(puzzleTable);
    
    let blob = new Blob([svgData], {type: "image/svg+xml;charset=utf-8"});
    let link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'puzzle.svg';
    link.click();
});
</script>
<script>
document.getElementById('downloadPuzzleAsJPG').addEventListener('click', function() {
    const puzzlePreview = document.querySelector('.puzzle-table');
    const scale = parseInt(document.getElementById('imageScale').value);
    const quality = parseFloat(document.getElementById('imageQuality').value);

    html2canvas(puzzlePreview, {
        scale: scale,
        useCORS: true,
        logging: true
    }).then(canvas => {
        let link = document.createElement('a');
        link.download = 'puzzle.jpg';
        link.href = canvas.toDataURL("image/jpeg", quality);
        link.click();
    });
});

</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
