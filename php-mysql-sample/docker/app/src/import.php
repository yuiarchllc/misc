<?php

ini_set('max_execution_time', '1200');

/**
 * DB接続
 */
try {
  $db = new PDO('mysql:dbname=testdb;host=db', 'user', 'pass');
} catch (\Exception $e) {
  echo "NG";
  var_dump($e->getMessage());
  exit;
}


/**
 * 銀行情報
 */
$ch = curl_init('https://zengin-code.github.io/api/banks.json');
curl_setopt_array($ch, [
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_SSL_VERIFYPEER => false,
  CURLOPT_RETURNTRANSFER => true,
]);

$response = curl_exec($ch);
$banks = json_decode($response, true);
curl_close($ch);

$db->query('TRUNCATE TABLE t_banks');
$bankCodes = [];
foreach ($banks as $bank) {
  $stmt = $db->prepare('INSERT INTO t_banks (code, name, kana, hira, roma) VALUES (:code, :name, :kana, :hira, :roma)');
  $stmt->execute([
    ':code' => $bank['code'] ?? '',
    ':name' => $bank['name'] ?? '',
    ':kana' => $bank['kana'] ?? '',
    ':hira' => $bank['hira'] ?? '',
    ':roma' => $bank['roma'] ?? '',
  ]);
  $bankCodes[] = $bank['code'];
}


/**
 * 支店情報（同期）
 */

// $db->query('TRUNCATE TABLE t_branches');
// foreach ($bankCodes as $bankCode) {
//   $ch = curl_init('https://zengin-code.github.io/api/branches/' . $bankCode . '.json');
//   curl_setopt_array($ch, [
//     CURLOPT_CUSTOMREQUEST => 'GET',
//     CURLOPT_SSL_VERIFYPEER => false,
//     CURLOPT_RETURNTRANSFER => true,
//   ]);
  
//   $response = curl_exec($ch);
//   $branches = json_decode($response, true);
//   curl_close($ch);
//   foreach ($branches as $branch) {
//     $stmt = $db->prepare('INSERT INTO t_branches (bank_code, code, name, kana, hira, roma) VALUES (:bank_code, :code, :name, :kana, :hira, :roma)');
//     $stmt->execute([
//       ':bank_code' => $bankCode,
//       ':code' => $branch['code'] ?? '',
//       ':name' => $branch['name'] ?? '',
//       ':kana' => $branch['kana'] ?? '',
//       ':hira' => $branch['hira'] ?? '',
//       ':roma' => $branch['roma'] ?? '',
//     ]);
//   }
// }


/**
 * 支店情報（非同期）
 */

$db->query('TRUNCATE TABLE t_branches');
$chs = [];
foreach ($bankCodes as $bankCode) {
  $ch = curl_init('https://zengin-code.github.io/api/branches/' . $bankCode . '.json');
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_RETURNTRANSFER => true,
  ]);
  $chs[$bankCode] = $ch;
}

$chChunks = array_chunk($chs, 5, true);
foreach ($chChunks as $chChunk) {
  $mh = curl_multi_init();
  foreach ($chChunk as $ch) {
    curl_multi_add_handle($mh, $ch);
  }

  do {
    curl_multi_exec($mh, $running);
  } while ($running);

  foreach ($chChunk as $bankCode => $ch) {
    $response = curl_multi_getcontent($ch);
    $branches = json_decode($response, true);
    curl_close($ch);
    foreach ($branches as $branch) {
      $stmt = $db->prepare('INSERT INTO t_branches (bank_code, code, name, kana, hira, roma) VALUES (:bank_code, :code, :name, :kana, :hira, :roma)');
      $stmt->execute([
        ':bank_code' => $bankCode,
        ':code' => $branch['code'] ?? '',
        ':name' => $branch['name'] ?? '',
        ':kana' => $branch['kana'] ?? '',
        ':hira' => $branch['hira'] ?? '',
        ':roma' => $branch['roma'] ?? '',
      ]);
    }
  }
  curl_multi_close($mh);
}

// INSERT INTO t_branches(bank_code, code, name, kana, hira, roma) SELECT 'A001', code, name, kana, hira, roma FROM t_branches;
// SELECT * FROM t_branches WHERE roma = 'funabashi';
// ALTER TABLE t_branches ADD INDEX test (roma);