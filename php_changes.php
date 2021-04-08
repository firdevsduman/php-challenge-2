<?php

# değişkenleri, sabitleri tanımla
$inboxPath = __DIR__ . '/data/tmp';
$outboxPath = __DIR__ . '/data/out';
$filePathList = glob("$inboxPath/*.txt");
$fileCount = count($filePathList);

#her bir dosyaya :
foreach ($filePathList as $filePath) {
    # dosya içeriğini satır satır array e dök
    $fileLines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    # header ve detail title & value eşleşmelerini yap
    foreach ($fileLines as $i => $line) {
        $fileLines[$i] = explode(';', $line);

        #burda bizden herhangi bir koşul sağlanması istenmediği ve sadece verilerin format değişikliği istendiği için kod düznlendi.
        if ($i === 1) {
            $line = array_combine($fileLines[0], $fileLines[1]);
            $line['dateCreated'] = DateTime::createFromFormat('YmdHis', $line['dateCreated'])->format(
                'Y-m-d H:i:s'
            );
            $line['dateSend'] = DateTime::createFromFormat('YmdHis', $line['dateSend'])->format(
                'Y-m-d H:i:s'
            );
            $xmlArray['order']['header'] = $line;
        }
        if ($i > 2) {
            $line = array_combine($fileLines[2], $fileLines[$i]);

            #burda sadece belirli verilerin boş olup olmadığı denetlendi
            #aksi durumda hepsini denetlediğimizde csv dosyasında bulunan boş satırları direk sildiği görüldü
            #Fakat bizden boş olduğu halde silinmesi istenmeyen yerler için kontrol gereksiz bulundu ve düzeltildi.
            if (!empty($line['itemCode'])
                && !empty($line['price'])
                && substr_count($line['price'], ',') < 2
                && 0 !== strpos($line['price'], ',')) {
                $turkishCharacters=array('ı','İ','ö','Ö','ü','Ü','ç','Ç','ş','Ş','ğ','Ğ');
                $englishCharacters=array('i','I','o','O','u','U','c','C','s','S','g','G');
                $line['itemDescription']=str_replace($turkishCharacters,$englishCharacters,$line['itemDescription']);

                $line['deliveryDateLatest'] = DateTime::createFromFormat('dMyy', $line['deliveryDateLatest'])->format(
                    'Ymd '
                );

                $xmlArray['order']['lines'][] = $line;
            }
        }
    }

    # xml oluşturuldu
    $xml = new SimpleXMLElement('<order/>');

    $header = $xml->addChild('header');
    foreach ($xmlArray['order']['header'] as $key => $value) {
        $header->addChild($key, $value);
    }

    $lines = $xml->addChild('lines');
    array_map(
        function ($item) use ($lines) {
            $line = $lines->addChild('line');
            foreach ($item as $key => $value) {
                $line->addChild($key, $value);
            }
        },
        $xmlArray['order']['lines']
    );

    $dom = dom_import_simplexml($xml)->ownerDocument;
    $dom->formatOutput = true;

    if (false === file_put_contents("$outboxPath/output.xml", $dom->saveXML())) {
        echo "output dosya yazılamadı!" . PHP_EOL;
        exit(1);
    }

    echo "output yaratıldı!" . PHP_EOL;
}

# fin!
