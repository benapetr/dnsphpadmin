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
    ['abc123.com', 'abc123.com'], // Non-IDN domain for control test
];

// Test cases for full FQDN conversions with multiple labels
$idn_fqdn_test_cases = [
    // Format: [UTF8 FQDN, ASCII/Punycode FQDN]
    ['www.bücher.de', 'www.xn--bcher-kva.de'],
    ['тест.пример', 'xn--e1awd7f.xn--e1afmkfd'],
    ['test.例え.テスト', 'test.xn--r8jz45g.xn--zckzah'],
    ['café.example.fr', 'xn--caf-dma.example.fr'],
];
