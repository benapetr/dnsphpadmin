<?php
// Test data for IDN domain testing

$idn_test_cases = [
    // Format: [UTF8 domain, ASCII/Punycode domain]
    ['henriknordström.se', 'xn--henriknordstrm-7pb.se'],
    ['bücher.de', 'xn--bcher-kva.de'],
    ['例え.テスト', 'xn--r8jz45g.xn--zckzah'],
    ['россия', 'xn--p1ai'],
    ['café.fr', 'xn--caf-dma.fr'],
    ['münchen.de', 'xn--mnchen-3ya.de'],
    ['mañana.com', 'xn--maana-pta.com'],
    ['faß.de', 'xn--fa-hia.de'],
    ['straße.de', 'xn--strae-oqa.de'],
    ['кошка.рф', 'xn--80aaxitdb.xn--p1ai'],
    ['مثال.إختبار', 'xn--mgbh0fb.xn--kgbechtv'],
    ['例子.测试', 'xn--fsq.xn--0zwm56d'],
    ['παράδειγμα.δοκιμή', 'xn--hxajbheg2az3al.xn--jxalpdlp'],
    ['उदाहरण.परीक्षा', 'xn--p1b6ci4b4b3a.xn--11b5bs3a9aj6g'],
    ['مثال.آزمایشی', 'xn--mgbh0fb.xn--hgbk6aj7f53bba'],
    ['abc.def', 'abc.def'],
    ['abc123.com', 'abc123.com'], // Non-IDN domain for control test
];

// Test cases for full FQDN conversions with multiple labels
$idn_fqdn_test_cases = [
    // Format: [UTF8 FQDN, ASCII/Punycode FQDN]
    ['www.bücher.de', 'www.xn--bcher-kva.de'],
    ['тест.пример', 'xn--e1awd7f.xn--e1afmkfd'],
    ['test.例え.テスト', 'test.xn--r8jz45g.xn--zckzah'],
    ['café.example.fr', 'xn--caf-dma.example.fr'],
    ['mail.δοκιμή.gr', 'mail.xn--jxalpdlp.gr'],
    ['sub.例子.测试', 'sub.xn--fsq.xn--0zwm56d'],
    ['www.faß.de', 'www.xn--fa-hia.de'],
    ['shop.उदाहरण.परीक्षा', 'shop.xn--p1b6ci4b4b3a.xn--11b5bs3a9aj6g'],
    ['test.пример.рф', 'test.xn--e1afmkfd.xn--p1ai'],
    ['www.abc123.com', 'www.abc123.com'],
];
