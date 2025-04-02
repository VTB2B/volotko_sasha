<?php
function canFitInBag($bagDimensions, $itemDimensions) {

    sort($bagDimensions);
    sort($itemDimensions);
    

    return ($itemDimensions[0] <= $bagDimensions[0]) &&
           ($itemDimensions[1] <= $bagDimensions[1]) &&
           ($itemDimensions[2] <= $bagDimensions[2]);
}


$bagHeight = 10;
$bagLength = 20;
$bagWidth = 25;

$itemHeight = 15;
$itemLength = 10;
$itemWidth = 24;


$bagDimensions = [$bagHeight, $bagLength, $bagWidth];
$itemDimensions = [$itemHeight, $itemLength, $itemWidth];


if (canFitInBag($bagDimensions, $itemDimensions)) {
    echo "Товар помещается в сумку.";
} else {
    echo "Товар не помещается в сумку.";
}
?>