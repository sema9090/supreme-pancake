<?php
namespace Gaz;

use Exception,DateTime,DateInterval;

abstract class XmlParser
{
    public $file;
    public $xmlString;
    public $array;
    public $pattern = '/[A-Za-zА-Яа-яЁё]{3,}/mu';

    public function __construct($file)
    {
        if( is_null($file) ) {
            throw new Exception('$file пуст');
        }
        $this->file = $file;
        $this->getXmlString();
        $this->getParsedArray();
    }

    /**
     * из урл или файла получаем строку
     */
    public function getXmlString()
    {
        if( $this->checkUrl() ) {
            $ch = curl_init($this->file);
            if(strtolower((substr($this->file,0,5))=='https')) { // если соединяемся с https
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36');

            $this->httpСode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->xmlString = curl_exec($ch);
            curl_close($ch);
        }else{
            $this->xmlString = file_get_contents($this->file);;
        }
    }
    /**
     * проверяет строку на соответствие урл
     * @return bool
     */
    public function checkUrl(): bool
    {
        if( preg_match('#((https?|ftp)://(\S*?\.\S*?))([\s)\[\]{},;"\':<]|\.\s|$)#i', $this->file, $matches, PREG_OFFSET_CAPTURE, 0) ) {
            return true;
        }
        return false;
    }

    /**
     * поиск в массиве значения по ключу
     * @param $searchKey
     * @param array $arr
     * @param array $result
     * @return array
     */
    static function searchKey($searchKey, array $arr, array &$result)
    {
        if (isset($arr[$searchKey])) {
            $result[] = $arr[$searchKey];
        }

        foreach ($arr as $key => $param) {
            if (is_array($param)) {
                self::searchKey($searchKey, $param, $result);
            }
        }
        return $result;
    }

    /**
     * возвращает колво постов
     * @return int
     */
    public function getCountPosts(): int
    {
        return count($this->array);
    }

    /**
     * возвращает колво слов
     * @return int
     */
    public function getAverageWordsNumber():int
    {
        $arrWords = [];
        foreach ($this->array as $value) {
            if(preg_match_all($this->pattern, $value['title'].' '.$value['description'], $matches, PREG_SET_ORDER, 0)) {
                $arrWords[] = count($matches);
            }
        }
        return (int)round(array_sum($arrWords) / sizeof($arrWords), 0);
    }

    /**
     * возвращает список категорий
     * @return array
     */
    public function getCategoryList(): array
    {
        $categoryList = [];
        foreach ($this->array as $value) {
            if(!in_array($value['category'],$categoryList) && $value['category']) {
                $categoryList[] = $value['category'];
            }
        }
        return $categoryList;
    }

    /**
     * html список
     * @param $list
     * @return string
     */
    public static function outputList($list): string
    {
        $html = '';
        if(is_array($list)) {
            $html .= '<ul><li>';
            $html .= implode('</li><li>', $list);
            $html .= '</li></ul>';
        }
        return $html;
    }

    /**
     * возвращает отсортированный список категорий с количеством постов
     * @return array
     */
    public function getCategoryCountPosts(): array
    {
        $categoryList =  [];
        foreach ($this->array as $value) {
            if($value['category']) {
                if( $categoryList[$value['category']] ) {
                    $categoryList[$value['category']] = $categoryList[$value['category']]+1;
                } else {
                    $categoryList[$value['category']] = 1;
                }
            }
        }
        arsort($categoryList, SORT_NUMERIC);

        $result = array_map(function($k, $v){
            return $k.': '.$v;
        }, array_keys($categoryList), array_values($categoryList));

        return $result;
    }

    /**
     * возвращает список популярных слов и их количество
     * @return array
     */
    public function getMostPopularWordList(): array
    {
        $arrWords = [];
        foreach ($this->array as $value) {
            if(preg_match_all($this->pattern, $value['title'].' '.$value['description'], $matches, PREG_SET_ORDER, 0)) {
                foreach ($matches as $val) {
                    $valLower = mb_strtolower($val[0], 'UTF-8');
                    if( $arrWords[$valLower] ) {
                        $arrWords[$valLower] = $arrWords[$valLower]+1;
                    } else {
                        $arrWords[$valLower] = 1;
                    }
                }
            }
        }
        arsort($arrWords, SORT_NUMERIC);
        $arrWords = array_slice($arrWords, 0, 10);

        $result = array_map(function($k, $v){
            return $k.': '.$v;
        }, array_keys($arrWords), array_values($arrWords));

        return $result;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getPostLastDate(): array
    {
        list($postLastDate,) = $this->array;

        $datePub = new DateTime($postLastDate['pubDate']);

        $dateNow = new DateTime();
        $dateDiff = $dateNow->diff($datePub);
        $result = [
          'Дата последнего поста:'.$datePub->format('d-m-Y H:i:s'),
          'Времени прошло: '.$dateDiff->format('%y лет %m месяцев %a дней %h часов %i минут %s секунд')
        ];
        return $result;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getAssumedTimeNextPost(): string
    {
        list($postLastDate,) = $this->array;

        foreach ($this->array as $value) {
            if($datePrev) {
                $datePub = new DateTime($value['pubDate']);
                $diffInSeconds = $datePrev->getTimestamp() - $datePub->getTimestamp();
                if($diffInSeconds > 0) {
                    $diffDates = $datePrev->diff($datePub);
                    $total_minutes = ($diffDates->days * 24 * 60);
                    $total_minutes += ($diffDates->h * 60);
                    $total_minutes += $diffDates->i;
                    $dateDiff[] = $total_minutes;
                }
            }
            $datePrev = new DateTime($value['pubDate']);
        }

        $minToAdd = (int)round(array_sum($dateDiff) / sizeof($dateDiff), 0);
        $datePub = new DateTime($postLastDate['pubDate']);
        $datePub->add(new DateInterval('PT' . $minToAdd . 'M'));
        return $datePub->format('d-m-Y H:i:s');
    }

    public function getLetterCount()
    {
        $patt = '~(?<vowels>[аеёиоуыэюя])|(?<conson>[бвгджзйклмнпрстфхцчшщ])~iu';

        foreach ($this->array as $value) {
            if(preg_match_all($patt, $value['title'].' '.$value['description'] , $matches)) {
                $letters['vowels'][] = count(array_filter($matches['vowels']));
                $letters['conson'][] = count(array_filter($matches['conson']));
            }
        }
        return [
            'Среднее колво гласных в посте: '.(int)round(array_sum($letters['vowels']) / sizeof($letters['vowels']), 0),
            'Среднее колво согласных в посте: '.(int)round(array_sum($letters['conson']) / sizeof($letters['conson']), 0),
        ];
    }
    /**
     * вывод html
     * @return string
     */
    public function outputInfo(): string
    {
        $html = '<div style=\'display: flex;justify-content: space-between;margin-bottom: 20px;\'>';
        $html .= '<div style=\'background-color: #8D858531;padding: 5px;\'>Количество постов: '.$this->getCountPosts().'</div>';
        $html .= '<div style=\'background-color: #8D858531;padding: 5px;\'>Cреднее количество слов в посте: '.$this->getAverageWordsNumber().'</div>';
        $html .= '<div style=\'background-color: #8D858531;padding: 5px;\'>Список категорий постов: '.self::outputList($this->getCategoryList()).'</div>';
        $html .= '<div style=\'background-color: #8D858531;padding: 5px;\'>Количество постов в категории: '.self::outputList($this->getCategoryCountPosts()).'</div>';
        $html .= '<div style=\'background-color: #8D858531;padding: 5px;\'>Популярные слова с количеством: '.self::outputList($this->getMostPopularWordList()).'</div>';
        $html .= '<div style=\'background-color: #8D858531;padding: 5px;\'>'.self::outputList($this->getPostLastDate()).'</div>';
        $html .= '<div style=\'background-color: #8D858531;padding: 5px;\'>Следующий пост будет примерно в: '.$this->getAssumedTimeNextPost().'</div>';
        $html .= '<div style=\'background-color: #8D858531;padding: 5px;\'>'.self::outputList($this->getLetterCount()).'</div>';
        $html .= '</div>';
        return $html;
    }
    abstract function getParsedArray();
}
