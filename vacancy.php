<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");

if (!$USER->IsAdmin()) {
    LocalRedirect('/');
}

\Bitrix\Main\Loader::includeModule('iblock');

$IBLOCK_ID = 5;
$csvFile = $_SERVER['DOCUMENT_ROOT'] . "/local/php_interface/vacancy.csv";

$rsElements = CIBlockElement::GetList([], ['IBLOCK_ID' => $IBLOCK_ID], false, false, ['ID']);
while ($element = $rsElements->GetNext()) {
    CIBlockElement::Delete($element['ID']);
}

$arProps = [];
$rsProp = CIBlockPropertyEnum::GetList(
    ["SORT" => "ASC", "VALUE" => "ASC"],
    ['IBLOCK_ID' => $IBLOCK_ID]
);
while ($arProp = $rsProp->Fetch()) {
    $arProps[$arProp['PROPERTY_CODE']][trim($arProp['VALUE'])] = $arProp['ID'];
}

if (($handle = fopen($csvFile, "r")) !== false) {
    $row = 1;
    $el = new CIBlockElement;

    while (($data = fgetcsv($handle, 1000, ",")) !== false) {
        if ($row == 1) {
            $row++;
            continue;
        }
        $row++;

        if (empty($data[3])) {
            echo "Пропущена строка №$row: нет названия вакансии.<br>";
            continue;
        }

        $PROP = [
            'ACTIVITY' => trim($data[9] ?? ''),
            'FIELD' => trim($data[11] ?? ''),
            'OFFICE' => trim($data[1] ?? ''),
            'LOCATION' => trim($data[2] ?? ''),
            'REQUIRE' => trim($data[4] ?? ''),
            'DUTY' => trim($data[5] ?? ''),
            'CONDITIONS' => trim($data[6] ?? ''),
            'EMAIL' => trim($data[12] ?? ''),
            'DATE' => date('d.m.Y'),
            'TYPE' => trim($data[8] ?? ''),
            'SALARY_TYPE' => '',
            'SALARY_VALUE' => trim($data[7] ?? ''),
            'SCHEDULE' => trim($data[10] ?? ''),
        ];

        if ($PROP['SALARY_VALUE'] === '-' || $PROP['SALARY_VALUE'] === 'по договоренности') {
            $PROP['SALARY_VALUE'] = '';
            $PROP['SALARY_TYPE'] = $arProps['SALARY_TYPE']['договорная'] ?? '';
        } else {
            $arSalary = explode(' ', $PROP['SALARY_VALUE']);
            if (in_array($arSalary[0], ['от', 'до'])) {
                $PROP['SALARY_TYPE'] = $arProps['SALARY_TYPE'][$arSalary[0]] ?? '';
                array_shift($arSalary);
                $PROP['SALARY_VALUE'] = implode(' ', $arSalary);
            } else {
                $PROP['SALARY_TYPE'] = $arProps['SALARY_TYPE']['='] ?? '';
            }
        }

        $arLoadProductArray = [
            "MODIFIED_BY" => $USER->GetID(),
            "IBLOCK_SECTION_ID" => false,
            "IBLOCK_ID" => $IBLOCK_ID,
            "PROPERTY_VALUES" => $PROP,
            "NAME" => $data[3],
            "ACTIVE" => end($data) ? 'Y' : 'N',
        ];

        if ($PRODUCT_ID = $el->Add($arLoadProductArray)) {
            echo "Добавлен элемент с ID: $PRODUCT_ID<br>";
        } else {
            echo "Ошибка при добавлении элемента: " . $el->LAST_ERROR . "<br>";
        }
    }
    fclose($handle);
} else {
    echo "Ошибка: не удалось открыть файл $csvFile.<br>";
}
?>
