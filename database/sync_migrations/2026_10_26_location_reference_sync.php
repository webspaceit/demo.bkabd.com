<?php

// Allow reference-only geography rows on demo without fabricated user links.
return [
    "ALTER TABLE `divisions` MODIFY COLUMN `user_id` int DEFAULT NULL",
    "ALTER TABLE `districts` MODIFY COLUMN `user_id` int DEFAULT NULL",
    "ALTER TABLE `upozilas` MODIFY COLUMN `user_id` int DEFAULT NULL",
    "ALTER TABLE `unions` MODIFY COLUMN `user_id` int DEFAULT NULL",
];
