<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

$surse = [
    'libris' => 'https://www.libris.ro/carti/it-computere',
];

$antetCsv = [
    'url',
    'titlu',
    'autor',
    'imagine',
    'pret',
    'pret vechi',
    'id extern'
];

$folderDate = 'data/' . date('Y-m-d-H-i-s'); // Include timpul în numele folderului
if (!is_dir($folderDate)) {
    if (!mkdir($folderDate, 0777, true)) {
        echo "Nu s-a putut crea folderul de date.";
        exit();
    }
}

echo "Data scraping începută la - " . date('Y-m-d H:i:s') . "\n";

$dataToSend = []; // Array to store the scraped data

foreach ($surse as $numeSursa => $sursa) {
    if ($numeSursa !== 'libris') {
        continue; // Sari peste sursele diferite de 'libris'
    }

    $nextUrl = $sursa;
    $segmenteNextUrl = parse_url($nextUrl);
    $baseUrl = $segmenteNextUrl['scheme'] . '://' . $segmenteNextUrl['host'];
    $numarPagina = 0;
    $fisierDate = $folderDate . '/' . $numeSursa . '.csv';

    echo "Scraping sursa: $numeSursa \n";

    $fp = fopen($fisierDate, 'w');
    if ($fp === false) {
        echo "Nu s-a putut deschide fișierul pentru scriere: $fisierDate";
        continue;
    }
    fputcsv($fp, $antetCsv);

    while ($nextUrl) {
        try {
            $continut = file_get_contents($nextUrl);

            switch ($numeSursa) {
                case 'libris':
                    $selectorProduse = '/<li class="categ-prod-item.*?<\/li>/s';
                    preg_match_all($selectorProduse, $continut, $potriviriProduse);
                    preg_match_all('/<li class="pagination-item.*?href="([^"]*)"/', $continut, $potriviriNext);
                    $nextUrl = count($potriviriNext[1]) > 0 ? $baseUrl . $potriviriNext[1][count($potriviriNext[1]) - 1] : false;
                    break;
            }

            foreach ($potriviriProduse[0] as $htmlProdus) {
                switch ($numeSursa) {
                    case 'libris':
                        preg_match('/<img[^>]*data-echo="([^"]*)"[^>]*>/', $htmlProdus, $potriviriImagine);
                        $imagine = $potriviriImagine[1];

                        preg_match('/<h2[^>]*>(.*?)<\/h2>/', $htmlProdus, $potriviriTitlu);
                        $titlu = $potriviriTitlu[1];

                        if (strpos($htmlProdus, 'price-reduced') !== false) {
                            // Produsul are preț redus
                            echo '<div class="produs">
                                    <img src="' . $imagine . '" alt="' . $titlu . '">
                                    <h3>' . $titlu . '</h3>                
                                  </div>';
                        } else {
                            // Produsul nu are preț redus
                            echo '<div class="produs">
                                    <img src="' . $imagine . '" alt="' . $titlu . '">
                                    <h3>' . $titlu . '</h3>
                                    <span class="pret">' . $pret . '</span>
                                  </div>';
                        }

                        preg_match('/data-id="([^"]*)"/', $htmlProdus, $potriviriId);
                        $id = $potriviriId[1];
                        break;
                }


                $linieCsv = [
                    $titlu,
                    $imagine,
                    $id
                ];
                fputcsv($fp, $linieCsv);


                $dataToSend[] = [
                    'url' => $sursa,
                    'titlu' => $titlu,
                    'autor' => '',
                    'imagine' => $imagine,
                    'pret' => '',
                    'id extern' => $id
                ];
            }

            if ($numarPagina % 5 === 0) {
                sleep(3);
            }

            $numarPagina++;

            echo "Pagina scrapată IT Computere \n";
        } catch (Exception $e) {
            echo "A apărut o excepție la cerere: " . $e->getMessage() . "\n";
        }
    }

    fclose($fp);
}

// Convert the data to JSON
$jsonData = json_encode($dataToSend);

if (isset($_POST['submit_delete'])) {
    // Create a POST request to send the JSON data to the API server
    $apiUrl = 'http://localhost:4000/api/delete'; // Replace with the correct API URL

    // Make sure to adjust the request payload according to the API's requirements
    $requestPayload = [
        'data' => $jsonData
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_POSTFIELDS => http_build_query($requestPayload),
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        echo "Date sterse API server. ";
    }
    $apiUrl = 'http://localhost:8080/rdf4j-server/repositories/libris/statements';

    // SPARQL query to delete all data
    $query = 'DELETE { ?s ?p ?o } WHERE { ?s ?p ?o }';

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'update=' . urlencode($query),
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        echo "All data deleted from RDF4J.";
    }
}

if (isset($_POST['submit_rdf4j'])) {
    // Convert the PHP array to JSON
    $jsonData = json_encode($dataToSend);

    // Echo the data being sent
    echo "Data being sent:\n";
    echo $jsonData;
    echo "\n\n";

    // Set up the cURL request to send data to the RDF4J server
    $rdf4jUrl = 'http://localhost:8080/rdf4j-server/repositories/libris/statements';

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $rdf4jUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        echo "Data inserted successfully from RDF4J server. http://localhost:8080/rdf4j-server/repositories/libris/statements Response: " . $response;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // Convert the data to JSON
    $jsonData = json_encode($dataToSend);



    // Set up the cURL request to send data to the Node.js application
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, 'http://localhost:4000/api');
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);


    $response = curl_exec($curl);


    if ($response === false) {
        $error = curl_error($curl);
        echo 'cURL error: ' . $error;
    } else {
        echo 'Data trimisa pe serverul JSON.';
    }


    curl_close($curl);
}




?>

<!DOCTYPE html>
<html>

<head>
    <title>Send Data</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f2f2f2;
    }

    form {
        margin-top: 20px;
        text-align: center;
    }

    button {
        padding: 10px 20px;
        font-size: 16px;
        border-radius: 4px;
        background-color: #4CAF50;
        color: white;
        border: none;
        cursor: pointer;
        margin: 0 10px;
    }

    button:hover {
        background-color: #45a049;
    }
    </style>
</head>

<body>

    <form method="post" action="">
        <button type="submit" name="submit">Send Data to API Server</button>
        <button type="submit" onclick="sendToRDF()" name="submit_rdf4j">Send Data to RDF4J Server</button>
    </form>

    <form method="post" action="">
        <button type="submit" name="submit_delete">Delete Data</button>
    </form>
</body>
<script>
function sendToRDF() {
    const jsonData = <?php echo json_encode($dataToSend); ?>;
    console.log(jsonData);

    let query = '';
    // Modify this code block

    jsonData.forEach(triple => {
        const subject = `<http://carti.com/${encodeURIComponent(triple.titlu)}>`;
        const predicate = `<http://carti.com/titlu>`;
        const object = `"${triple.titlu}"`;

        query += `INSERT DATA { ${subject} ${predicate} ${object} } ;\n`;
    });


    fetch('http://localhost:8080/rdf4j-server/repositories/libris/statements', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'update=' + encodeURIComponent(query)
        })
        .then(response => response.text())
        .then(data => {
            // Display the response message
            alert("Trimis pe RDF4J. A se verifica si Inspect/Console pentru datele afisate.");
        })
        .catch(error => {
            console.error('Error:', error);
        });
}
</script>

</html>