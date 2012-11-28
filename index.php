<?php
header('Content-Type: text/html;charset=windows-1251');
ini_set('display_errors', 'off');
ini_set('error_reporting', '8191');
if (array_key_exists('subject', $_GET) && $_GET['subject'] > 0) {
    include_once(dirname(__FILE__) . '/libs/download.php');
    include_once(dirname(__FILE__) . '/libs/phpQuery.class.php');
    $data = download('http://sudrf.ru/index.php?id=300&act=go_search&court_name=&court_addr=&court_type=0&court_okrug=0&court_subj=' . $_GET['subject'] . '&vcourt_okrug=0&search=%CD%E0%E9%F2%E8');
    phpQuery::newDocumentHTML($data, 'Windows-1251');
    $ul = pq('.search-results>li');
    $regions = array();
    $regionname = array();
    $get = $_GET;
    unset($get['subject']);
    foreach ($ul as $li) {
        $url = pq('div>div>a', $li)->attr('href');
        $regions[$url . '/modules.php?' . http_build_query($get, null, '&')] = array(
            'name' => mb_convert_encoding(pq('a:first', $li)->text(), 'cp1251','utf-8' ),
            'site' => $url,
            'url' => $url . '/modules.php?' . http_build_query($get, null, '&'),
        );
        $regionname[parse_url($url, PHP_URL_HOST)] = $regions[$url . '/modules.php?' . http_build_query($get, null, '&')]['name'];
    }
    if (sizeof($regions) > 0) {
        /* $regions = array_keys($regions);
          foreach($regions as $region){
          echo'<a href="'.$region.'">'.$region.'</a><br />';
          }
          die; */
        //Нашлись регионы, делаем параллельный запрос по всем регионеам и собираем ответы
        $responces = array();
        $urls_second = array();
        download_multi(array_keys($regions), array(), 'download_callback');
        if (sizeof($urls_second) > 0) {
            //ЗАпрос по остальным страницам
            download_multi($urls_second, array(), 'download_callback');
        }
        unset($regions);
    }
}
function download_callback($url, $body) {
    global $responces;
    global $urls_second;
    global $regionname;
    $onpage = 25;
    phpQuery::newDocumentHTML($body, 'Windows-1251');
    preg_match('|дел по запросу всего: (\d+)|is', $body, $match);
    if(sizeof($match)>1){
        $count = intval($match[1]);
    }
    else{
        $count = 1;
    }
    //echo ' count:'.$count.PHP_EOL;
    if ($count > $onpage) {
        //Несколько страниц
        parse_str(parse_url($url, PHP_URL_QUERY), $vars);
        if (!array_key_exists('start', $vars) || $vars['start'] == 0) {
            //Это первая страница и есть другие - их надо добавить во второй круг запросов
            $i = $onpage;
            do {
                $urls_second[] = $url . '&start=' . $i;
                $i+=$onpage;
            } while ($i < $count);
        }
    }
    $trs = pq('#tablcont>tr');
    $data = array();
    $i = 0;
    $section = 'criminal';
    $host = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . '/';
    foreach ($trs as $tr) {
        $i++;
        if ($i == 1)
            continue;
        if (pq('td', $tr)->size() == 1) {
            //Это заголовок
            $text = trim(iconv('utf-8', 'cp1251//TRANSLIT', pq('td', $tr)->text()));
            if (strpos($text, 'Уголовные') !== false) {
                $section = 'criminal';
            } elseif (strpos($text, 'Гражданские') !== false) {
                $section = 'civil';
            } elseif (strpos($text, 'административных') !== false) {
                $section = 'administrative';
            }
            continue;
        }
        if (pq('td', $tr)->size() == 8) {
            //Невский район, дургая схема
            // http://nvs.spb.sudrf.ru/modules.php?name=sud_delo&op=rs&FULL_NUMBER=&NAME=&ENTRY_DATE_FROM=23.10.2012&ENTRY_DATE_TO=23.10.2012&RESULT_DATE_FROM=&RESULT_DATE_TO=&INST_KIND=0
            parse_str(parse_url($url, PHP_URL_QUERY), $vars);
            $data = array(
                'link' => $host . pq('td:eq(1)>a', $tr)->attr('href'),
                'nomber' => iconv('utf-8', 'cp1251//TRANSLIT', pq('td:eq(1)>a', $tr)->text()),
                'info' => iconv('utf-8', 'cp1251//TRANSLIT', pq('td:eq(4)', $tr)->text()),
                'judge' => iconv('utf-8', 'cp1251//TRANSLIT', pq('td:eq(5)', $tr)->text()),
                'date_start' => $vars['ENTRY_DATE_FROM'],
                'date_finish' => '',
                'name' => $regionname[parse_url($url, PHP_URL_HOST)],
            );
        } else {
            $data = array(
                'link' => $host . pq('td:eq(0)>a', $tr)->attr('href'),
                'nomber' => iconv('utf-8', 'cp1251//TRANSLIT', pq('td:eq(0)>a', $tr)->text()),
                'info' => iconv('utf-8', 'cp1251//TRANSLIT', pq('td:eq(1)', $tr)->text()),
                'judge' => iconv('utf-8', 'cp1251//TRANSLIT', pq('td:eq(2)', $tr)->text()),
                'date_start' => iconv('utf-8', 'cp1251//TRANSLIT', pq('td:eq(3)', $tr)->text()),
                'date_finish' => iconv('utf-8', 'cp1251//TRANSLIT', pq('td:eq(4)', $tr)->text()),
                'name' => $regionname[parse_url($url, PHP_URL_HOST)],
            );
        }
        $responces[$data['name']][$section][] = $data;
    }
}
?><!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=windows-1251" />
        <title></title>
        <script type="text/javascript" src="./datepicker.js" /></script>
        <style type="text/css">
            *{
                font-family:Tahoma;
            }
            .result{
                padding-left:160px;
            }
            .cityregion{
                text-align:left;
            }
            .datatable{
                border:1px solid #BBBBBB;
                width:650px;
            }
            .datatable td{
                text-align:left;
                border:1px solid #BBBBBB;
            }
            .datatable th{
                font-weight:bold;
                text-align:center;
            }
            .datatable td.title{
                text-align:center;
                background-color:#E5D3BB;
                font-weight:bold;
            }
        </style>
</head>
<body>
    <form action="" method="get" id="form_search" name="form_search">
        <h1 style="color:#2269A2;font-size:20px;">Параметры поиска</h1>
        <input type="hidden" name="name" value="sud_delo" />
        <input type="hidden" name="op" value="rs" />
        <table style="border: 1px solid rgb(229, 229, 229);" style="margin-left:160px;" border="0" cellpadding="4" cellspacing="1" >
            <tr>
                <td style="padding-left:5px;" valign="middle" nowrap="nowrap" colspan="2">
                    Номер дела (материала):
                </td>
                <td valign="middle" style="padding-right: 10px;">
                    <input name="FULL_NUMBER" id="FULL_NUMBER" size="35" style="width:100%;border:1px solid #e8d4b9;" type="text" class="txt2" value="" onchange="javascript: this.style.border='1px solid #e8d4b9';" />        </td>
            </tr>

            <tr>
                <td style="padding-left: 5px;" valign="middle" nowrap="nowrap" colspan="2">
                    Фамилия, наименование:
                </td>
                <td valign="middle" style="padding-right: 10px;">
                    <input name="NAME" id="NAME" size="35" style="width:100%;border:1px solid #e8d4b9;" type="text" class="txt2" value="" onchange="javascript: this.style.border='1px solid #e8d4b9';" />        </td>
            </tr>
            <tr>
                <td style="padding-left: 5px;" valign="middle" nowrap="nowrap">
                    Дата поступления:
                </td>
                <td align="right">с</td>
                <td valign="top" nowrap="nowrap" style="padding-right: 5px;">
                    <input name="ENTRY_DATE_FROM" type="text" size=10 class="Lookup"
                           style="width:100px; height:13px; border:1px solid #e8d4b9;" value="23.10.2012"><a href="javascript:show_calendar('form_search.ENTRY_DATE_FROM');"
                           ><img src='./modules.gif' height=19 border=0 align="top"/></a>&nbsp;&nbsp;
                    по&nbsp;<input name="ENTRY_DATE_TO" type="text" size=10 class="Lookup"
                                   style="width:100px; height:13px; border:1px solid #e8d4b9;" value="23.10.2012"><a href="javascript:show_calendar('form_search.ENTRY_DATE_TO');"
                                   ><img src='./modules.gif' height=19 border=0 align="top"/></a>        </td>
            </tr>
            <tr>
                <td style="padding-left: 5px;" valign="middle" nowrap="nowrap">
                    Дата рассмотрения:
                </td>
                <td align="right">с</td>
                <td valign="middle" nowrap="nowrap" style="padding-right: 5px;">
                    <input name="RESULT_DATE_FROM" type="text" size=10 class="Lookup"
                           style="width:100px; height:13px; border:1px solid #e8d4b9;" value=""><a href="javascript:show_calendar('form_search.RESULT_DATE_FROM');"
                           ><img src='./modules.gif' height=19 border=0 align="top"/></a>&nbsp;&nbsp;
                    по&nbsp;<input name="RESULT_DATE_TO" type="text" size=10 class="Lookup"
                                   style="width:100px; height:13px; border:1px solid #e8d4b9;" value=""><a href="javascript:show_calendar('form_search.RESULT_DATE_TO');"
                                   ><img src='./modules.gif' height=19 border=0 align="top"/></a>        </td>
            </tr>
            <tr>
                <td style="padding-left: 5px;" valign="middle" nowrap="nowrap" colspan="2">
                    Вид судопроизводства:
                </td>
                <td valign="middle" style="padding-right: 5px;">
                    <select name="INST_KIND" id="INST_KIND" style="width:100%;border:1px solid #e8d4b9;" class="txt2" onchange="javascript: this.style.border='1px solid #e8d4b9';" >
                        <option value="0" selected>все виды</option>
                        <option value="1" >уголовное</option>
                        <option value="2" >гражданское</option>
                        <option value="3" >административное</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td style="padding-left:5px;" valign="middle" nowrap="nowrap" colspan="2">
                    Субъект Российской Федерации:
                </td>
                <td valign="middle" style="padding-right: 10px;">
                    <select name="subject" style="width:100%;border:1px solid #e8d4b9;" class="txt2" onchange="javascript: this.style.border='1px solid #e8d4b9';">
                        <option value='0'></option>
                        <option value='22'>Алтайский край</option>
                        <option value='28'>Амурская область</option>
                        <option value='29'>Архангельская область</option>
                        <option value='30'>Астраханская область</option>
                        <option value='31'>Белгородская область</option>
                        <option value='32'>Брянская область</option>
                        <option value='33'>Владимирская область</option>
                        <option value='34'>Волгоградская область</option>
                        <option value='35'>Вологодская область</option>
                        <option value='36'>Воронежская область</option>
                        <option value='77'>Город Москва</option>
                        <option value='78'>Город Санкт-Петербург</option>
                        <option value='79'>Еврейская автономная область</option>
                        <option value='75'>Забайкальский край</option>
                        <option value='37'>Ивановская область</option>
                        <option value='38'>Иркутская область</option>
                        <option value='07'>Кабардино-Балкарская Республика</option>
                        <option value='39'>Калининградская область</option>
                        <option value='40'>Калужская область</option>
                        <option value='41'>Камчатский край</option>
                        <option value='09'>Карачаево-Черкесская Республика</option>
                        <option value='42'>Кемеровская область</option>
                        <option value='43'>Кировская область</option>
                        <option value='44'>Костромская область</option>
                        <option value='23'>Краснодарский край</option>
                        <option value='24'>Красноярский край</option>
                        <option value='45'>Курганская область</option>
                        <option value='46'>Курская область</option>
                        <option value='47'>Ленинградская область</option>
                        <option value='48'>Липецкая область</option>
                        <option value='49'>Магаданская область</option>
                        <option value='50'>Московская область</option>
                        <option value='51'>Мурманская область</option>
                        <option value='83'>Ненецкий автономный округ </option>
                        <option value='52'>Нижегородская область</option>
                        <option value='53'>Новгородская область</option>
                        <option value='54'>Новосибирская область</option>
                        <option value='55'>Омская область</option>
                        <option value='56'>Оренбургская область</option>
                        <option value='57'>Орловская область</option>
                        <option value='58'>Пензенская область</option>
                        <option value='59'>Пермский край</option>
                        <option value='25'>Приморский край</option>
                        <option value='60'>Псковская область</option>
                        <option value='01'>Республика Адыгея</option>
                        <option value='02'>Республика Алтай</option>
                        <option value='03'>Республика Башкортостан</option>
                        <option value='04'>Республика Бурятия</option>
                        <option value='05'>Республика Дагестан</option>
                        <option value='06'>Республика Ингушетия</option>
                        <option value='08'>Республика Калмыкия</option>
                        <option value='10'>Республика Карелия</option>
                        <option value='11'>Республика Коми</option>
                        <option value='12'>Республика Марий Эл</option>
                        <option value='13'>Республика Мордовия</option>
                        <option value='14'>Республика Саха (Якутия)</option>
                        <option value='15'>Республика Северная Осетия-Алания</option>
                        <option value='16'>Республика Татарстан </option>
                        <option value='17'>Республика Тыва</option>
                        <option value='19'>Республика Хакасия</option>
                        <option value='61'>Ростовская область</option>
                        <option value='62'>Рязанская область</option>
                        <option value='63'>Самарская область</option>
                        <option value='64'>Саратовская область</option>
                        <option value='65'>Сахалинская область</option>
                        <option value='66'>Свердловская область</option>
                        <option value='67'>Смоленская область</option>
                        <option value='26'>Ставропольский край</option>
                        <option value='68'>Тамбовская область</option>
                        <option value='69'>Тверская область</option>
                        <option value='95'>Территории за пределами РФ</option>
                        <option value='70'>Томская область</option>
                        <option value='71'>Тульская область</option>
                        <option value='72'>Тюменская область</option>
                        <option value='18'>Удмуртская Республика</option>
                        <option value='73'>Ульяновская область</option>
                        <option value='27'>Хабаровский край</option>
                        <option value='86'>Ханты-Мансийский автономный округ-Югра</option>
                        <option value='74'>Челябинская область</option>
                        <option value='20'>Чеченская Республика</option>
                        <option value='21'>Чувашская Республика </option>
                        <option value='87'>Чукотский автономный округ</option>
                        <option value='89'>Ямало-Ненецкий автономный округ</option>
                        <option value='76'>Ярославская область</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td align="center" colspan="3">
                    <input type="submit" class="booton" value="Найти" >
                    &nbsp;
                    <input name="Reset" type="reset" class="booton" value="Очистить" />

                </td>
            </tr>
        </table>
    </form>
    <div class="result">
        <?php
        if (isset($responces)) {
?><table><?php
            foreach ($responces as $region => $itemss) {
                ?>
                </table>
                <div class="cityregion"><?= $region; ?></div>
                <table class="datatable" cellpadding="0" cellspacing="0" >
                    <tr>
                        <th>Номер дела</th>
                        <th>Информация по делу</th>
                        <th>Судья</th>
                        <th>Поступило</th>
                        <th>Рассмотрено</th>
                    </tr>
                    <?php
                    foreach ($itemss as $section => $items) {
                        ?><tr>
                            <td colspan="5" class="title"><?php
            if ($section == 'criminal') {
                echo'Уголовные дела';
            }
            if ($section == 'civil') {
                echo'Гражданские дела';
            }
            if ($section == 'administrative') {
                echo'Административные дела';
            }
                        ?></td>
                        </tr><?php
                    if (sizeof($items) > 0) {
                        foreach ($items as $item) {
                                ?>
                                <tr>
                                    <td><a href="<?= $item['link']; ?>" target="_blank"><?= $item['nomber']; ?></a></td>
                                    <td><?= $item['info']; ?></td>
                                    <td><?= $item['judge']; ?></td>
                                    <td><?= $item['date_start']; ?></td>
                                    <td><?= $item['date_finish']; ?></td>
                                </tr>
                                <?php
                            }
                        }
                    }
                }
                ?></table><?php
        }
            ?>
    </div>
</body>
</html>
