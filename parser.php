<?php
/*
GTIN standarts documentation
https://www.gs1.org/docs/idkeys/GS1_GTIN_Executive_Summary.pdf
*/

$parser = new GlobalTradeItemNumber('xml.json'); //path to GTIN excell converted to json file format
$parser::parse();

class GlobalTradeItemNumber
{
    protected static $conn;
    protected static $jsonData;
    protected static $codes = [];

    public function __construct($file)
    {
        $servername = "localhost";
        $username = "root";
        $password = "PWD";
        $dbname = "gs1";

        self::$conn = new mysqli($servername, $username, $password, $dbname);
        if (self::$conn->connect_error) {
            die("Connection failed: " . self::$conn->connect_error);
        }

        $fileContents = file_get_contents($file);
        self::$jsonData = json_decode($fileContents, true);
        self::$jsonData = isset(self::$jsonData['message']) ? self::$jsonData['message'] : self::$jsonData['StandardBusinessDocument']['message'];
        self::$jsonData = self::$jsonData['gs1Schema']['schema'];
    }

    //start parsing with first wanted element (segment)
    public static function parse()
    {
        self::selector(self::$jsonData, 'segment');
        return;
    }

    //select parsing style single array or multi arrays
    public static function selector($data, $key)
    {
        if (!isset($data[$key])) {
            return;
        }

        if (isset($data[$key]['@code'])) {
            self::$key($data[$key], false);
            unset(self::$codes[$key]);
            return;
        }

        foreach ($data[$key] as $si => $siv) {
            self::$key($data[$key][$si], $si);
            unset(self::$codes[$key]);
        }

        return;
    }

    //store element in to the database
    public static function store($func, $keyData, $keyIndex, $withFields = true)
    {
        $fields = '';
        if ($withFields) {
            $fields = implode(',', self::$codes);
            $fields = strlen($fields > 0) ? ',' . $fields : '';
        }

        $sql = 'INSERT IGNORE INTO ' . $func . ' VALUES ("' . $keyData['@code'] . '", "' . self::strEscape($keyData['@text']) . '", "' . self::strEscape($keyData['@definition']) . '"' . $fields . ')';

        if (self::$conn->query($sql) !== TRUE) {
            print_r(self::$codes);
            echo "\r\n";
            echo $func . " error " . $keyData['@code'] . " => " . $sql . "\r\n";
        }

        self::$codes[$func] = $keyData['@code'];

        return $keyIndex && isset($keyData[$keyIndex]) ? $keyData[$keyIndex] : $keyData;
    }

    //clear description
    private static function strEscape($str)
    {
        $str = str_replace('"', '\"', $str);
        return $str;
    }

    //segment parser
    private static function segment($keyData, $keyIndex)
    {
        $nextData = self::store(__FUNCTION__, $keyData, $keyIndex, false);
        return self::selector($nextData, 'family');
    }

    //family parser
    private static function family($keyData, $keyIndex)
    {
        $nextData = self::store(__FUNCTION__, $keyData, $keyIndex);
        return self::selector($nextData, 'class');
    }

    //class parser 
    private static function class($keyData, $keyIndex)
    {
        $nextData = self::store(__FUNCTION__, $keyData, $keyIndex);
        return self::selector($nextData, 'brick');
    }

    //brick parser
    private function brick($keyData, $keyIndex)
    {
        $nextData = self::store(__FUNCTION__, $keyData, $keyIndex);
        return self::selector($nextData, 'attType');
    }

    //attType parser
    private function attType($keyData, $keyIndex)
    {
        $nextData = self::store(__FUNCTION__, $keyData, $keyIndex);
        return self::selector($nextData, 'attValue');
    }

    //attValue parser
    private function attValue($keyData, $keyIndex)
    {
        $nextData = self::store(__FUNCTION__, $keyData, $keyIndex);
        return; //self::selector($nextData, 'attValue');
    }
}
