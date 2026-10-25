<?php

// Permit reference-only centers with no login account on demo.
return [
    "ALTER TABLE `centers` MODIFY COLUMN `user_id` int DEFAULT NULL",
];
