

CREATE TABLE `audit_trail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` enum('create','update','delete','approve','login','logout') NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_table` (`table_name`),
  CONSTRAINT `audit_trail_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=233 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO audit_trail VALUES ('1','1','login','users','1','','','::1','2026-01-03 00:30:47');
INSERT INTO audit_trail VALUES ('2','1','login','users','1','','','::1','2026-01-03 01:09:53');
INSERT INTO audit_trail VALUES ('3','1','logout','users','1','','','::1','2026-01-03 01:12:45');
INSERT INTO audit_trail VALUES ('4','1','login','users','1','','','::1','2026-01-03 01:13:55');
INSERT INTO audit_trail VALUES ('5','1','create','projects','1','','{\"project_name\":\"test\",\"location\":\"mumbai\",\"start_date\":\"2026-01-01\",\"expected_completion\":\"2026-01-10\",\"total_floors\":10,\"total_flats\":40,\"status\":\"completed\",\"created_by\":1}','::1','2026-01-03 01:19:38');
INSERT INTO audit_trail VALUES ('6','1','update','projects','1','','{\"project_name\":\"test\",\"location\":\"mumbai\",\"start_date\":\"2026-01-01\",\"expected_completion\":\"2026-01-10\",\"total_floors\":10,\"total_flats\":40,\"status\":\"active\"}','::1','2026-01-03 01:25:45');
INSERT INTO audit_trail VALUES ('7','1','create','parties','1','','{\"party_type\":\"customer\",\"name\":\"Prerak&#039;\",\"contact_person\":\"prerak\",\"mobile\":\"6350462627\",\"email\":\"patelprerak435@gmail.com\",\"address\":\"bhjb\",\"gst_number\":\"hhvhjvvyug87t8iu\"}','::1','2026-01-03 01:27:10');
INSERT INTO audit_trail VALUES ('8','1','create','bookings','1','','{\"flat_id\":1,\"customer_id\":1,\"project_id\":1,\"agreement_value\":8000,\"booking_date\":\"2026-01-03\",\"status\":\"active\",\"created_by\":1}','::1','2026-01-03 01:27:36');
INSERT INTO audit_trail VALUES ('9','1','create','payments','1','','{\"payment_type\":\"customer_receipt\",\"reference_type\":\"booking\",\"reference_id\":1,\"party_id\":1,\"payment_date\":\"2026-01-03\",\"amount\":400,\"payment_mode\":\"upi\",\"reference_no\":\"\",\"remarks\":\"\",\"created_by\":1}','::1','2026-01-03 01:27:56');
INSERT INTO audit_trail VALUES ('10','1','create','payments','2','','{\"payment_type\":\"customer_receipt\",\"reference_type\":\"booking\",\"reference_id\":1,\"party_id\":1,\"payment_date\":\"2026-01-03\",\"amount\":7600,\"payment_mode\":\"cash\",\"reference_no\":\"\",\"remarks\":\"\",\"created_by\":1}','::1','2026-01-03 01:28:11');
INSERT INTO audit_trail VALUES ('11','1','create','bookings','2','','{\"flat_id\":4,\"customer_id\":1,\"project_id\":1,\"agreement_value\":8000,\"booking_date\":\"2026-01-03\",\"status\":\"active\",\"created_by\":1}','::1','2026-01-03 01:40:43');
INSERT INTO audit_trail VALUES ('12','1','create','payments','3','','{\"payment_type\":\"customer_receipt\",\"reference_type\":\"booking\",\"reference_id\":2,\"party_id\":1,\"payment_date\":\"2026-01-03\",\"amount\":80,\"payment_mode\":\"cash\",\"reference_no\":\"\",\"remarks\":\"\",\"created_by\":1}','::1','2026-01-03 01:40:53');
INSERT INTO audit_trail VALUES ('13','1','create','materials','1','','{\"material_name\":\"cement\",\"unit\":\"kg\",\"default_rate\":300,\"current_stock\":400}','::1','2026-01-03 01:41:50');
INSERT INTO audit_trail VALUES ('14','1','update','materials','1','','{\"material_name\":\"cement\",\"unit\":\"kg\",\"default_rate\":300}','::1','2026-01-03 01:43:05');
INSERT INTO audit_trail VALUES ('15','1','login','users','1','','','::1','2026-01-03 09:42:27');
INSERT INTO audit_trail VALUES ('16','1','create','projects','2','','{\"project_name\":\"vantara\",\"location\":\"navi mumbai\",\"start_date\":\"2026-01-01\",\"expected_completion\":\"2026-07-08\",\"total_floors\":4,\"total_flats\":40,\"status\":\"active\",\"created_by\":1}','::1','2026-01-03 09:45:06');
INSERT INTO audit_trail VALUES ('17','1','create','parties','2','','{\"party_type\":\"customer\",\"name\":\"bhishmang\",\"contact_person\":\"party\",\"mobile\":\"7014793544\",\"email\":\"patelprerak435@gmail.com\",\"address\":\"optional\",\"gst_number\":\"\"}','::1','2026-01-03 09:48:08');
INSERT INTO audit_trail VALUES ('18','1','create','bookings','3','','{\"flat_id\":24,\"customer_id\":2,\"project_id\":2,\"agreement_value\":100000,\"booking_date\":\"2026-01-03\",\"status\":\"active\",\"created_by\":1}','::1','2026-01-03 09:49:09');
INSERT INTO audit_trail VALUES ('19','1','create','payments','4','','{\"payment_type\":\"customer_receipt\",\"reference_type\":\"booking\",\"reference_id\":3,\"party_id\":2,\"payment_date\":\"2026-01-03\",\"amount\":40000,\"payment_mode\":\"upi\",\"reference_no\":\"\",\"remarks\":\"\",\"created_by\":1}','::1','2026-01-03 09:49:37');
INSERT INTO audit_trail VALUES ('20','1','logout','users','1','','','::1','2026-01-03 10:01:28');
INSERT INTO audit_trail VALUES ('21','1','login','users','1','','','::1','2026-01-03 19:42:51');
INSERT INTO audit_trail VALUES ('22','1','delete','flats','2','','','::1','2026-01-03 19:43:11');
INSERT INTO audit_trail VALUES ('23','1','create','bookings','4','','{\"flat_id\":18,\"customer_id\":2,\"project_id\":2,\"agreement_value\":20000000,\"booking_date\":\"2026-01-03\",\"status\":\"active\",\"created_by\":1}','::1','2026-01-03 19:46:54');
INSERT INTO audit_trail VALUES ('24','1','login','users','1','','','::1','2026-01-03 19:57:36');
INSERT INTO audit_trail VALUES ('25','1','create','payments','5','','{\"payment_type\":\"customer_receipt\",\"reference_type\":\"booking\",\"reference_id\":4,\"party_id\":2,\"payment_date\":\"2026-01-03\",\"amount\":200000,\"payment_mode\":\"cash\",\"reference_no\":\"\",\"remarks\":\"\",\"created_by\":1}','::1','2026-01-03 19:58:11');
INSERT INTO audit_trail VALUES ('26','1','create','payments','6','','{\"payment_type\":\"customer_receipt\",\"reference_type\":\"booking\",\"reference_id\":4,\"party_id\":2,\"payment_date\":\"2026-01-03\",\"amount\":2000000,\"payment_mode\":\"cash\",\"reference_no\":\"\",\"remarks\":\"\",\"created_by\":1}','::1','2026-01-03 19:58:37');
INSERT INTO audit_trail VALUES ('27','1','login','users','1','','','::1','2026-01-03 20:23:35');
INSERT INTO audit_trail VALUES ('28','1','login','users','1','','','::1','2026-01-03 22:08:25');
INSERT INTO audit_trail VALUES ('29','1','login','users','1','','','::1','2026-01-04 12:06:47');
INSERT INTO audit_trail VALUES ('30','1','login','users','1','','','::1','2026-01-04 12:11:39');
INSERT INTO audit_trail VALUES ('31','1','create','material_usage','1','','{\"project_id\":1,\"material_id\":1,\"quantity\":10,\"usage_date\":\"2026-01-04\",\"remarks\":\"\",\"created_by\":1}','::1','2026-01-04 12:23:07');
INSERT INTO audit_trail VALUES ('32','1','create','parties','3','','{\"party_type\":\"vendor\",\"name\":\"ABC Cement Suppliers\",\"contact_person\":\"\",\"mobile\":\"\",\"email\":\"\",\"address\":\"123, Industrial Area\",\"gst_number\":\"\"}','::1','2026-01-04 12:32:25');
INSERT INTO audit_trail VALUES ('33','1','create','challans','1','','{\"challan_no\":\"MAT\\/2026\\/0001\",\"challan_type\":\"material\",\"party_id\":3,\"project_id\":1,\"challan_date\":\"2026-01-04\",\"total_amount\":90000,\"status\":\"pending\",\"created_by\":1}','::1','2026-01-04 12:33:36');
INSERT INTO audit_trail VALUES ('34','1','approve','challans','1','','','::1','2026-01-04 12:33:41');
INSERT INTO audit_trail VALUES ('35','1','create','payments','7','','{\"payment_type\":\"vendor_payment\",\"reference_type\":\"challan\",\"reference_id\":1,\"party_id\":3,\"payment_date\":\"2026-01-04\",\"amount\":90000,\"payment_mode\":\"cash\",\"reference_no\":\"\",\"remarks\":\"\",\"created_by\":1}','::1','2026-01-04 12:35:18');
INSERT INTO audit_trail VALUES ('36','1','create','challans','2','','{\"challan_no\":\"MAT\\/2026\\/0002\",\"challan_type\":\"material\",\"party_id\":3,\"project_id\":1,\"challan_date\":\"2026-01-04\",\"total_amount\":30000,\"status\":\"pending\",\"created_by\":1}','::1','2026-01-04 12:37:05');
INSERT INTO audit_trail VALUES ('37','1','login','users','1','','','::1','2026-01-05 11:35:41');
INSERT INTO audit_trail VALUES ('38','1','login','users','1','','','::1','2026-01-05 14:23:00');
INSERT INTO audit_trail VALUES ('39','1','create','users','3','','{\"username\":\"vaidik\"}','::1','2026-01-05 14:36:11');
INSERT INTO audit_trail VALUES ('40','1','logout','users','1','','','::1','2026-01-05 14:36:17');
INSERT INTO audit_trail VALUES ('41','3','login','users','3','','','::1','2026-01-05 14:36:22');
INSERT INTO audit_trail VALUES ('42','3','logout','users','3','','','::1','2026-01-05 14:38:37');
INSERT INTO audit_trail VALUES ('43','1','login','users','1','','','::1','2026-01-05 14:38:44');
INSERT INTO audit_trail VALUES ('44','1','create','users','4','','{\"username\":\"prerak\"}','::1','2026-01-05 14:39:24');
INSERT INTO audit_trail VALUES ('45','1','logout','users','1','','','::1','2026-01-05 14:39:28');
INSERT INTO audit_trail VALUES ('46','4','login','users','4','','','::1','2026-01-05 14:39:34');
INSERT INTO audit_trail VALUES ('47','4','logout','users','4','','','::1','2026-01-05 14:40:22');
INSERT INTO audit_trail VALUES ('48','1','login','users','1','','','::1','2026-01-05 14:40:30');
INSERT INTO audit_trail VALUES ('49','1','create','parties','4','','{\"party_type\":\"labour\",\"name\":\"ramu\",\"contact_person\":\"steel\",\"mobile\":\"88888888\",\"email\":\"something@gmail.com\",\"address\":\"none\",\"gst_number\":\"\"}','::1','2026-01-05 14:41:54');
INSERT INTO audit_trail VALUES ('50','1','create','challans','3','','{\"challan_no\":\"LAB\\/2026\\/0001\",\"challan_type\":\"labour\",\"party_id\":4,\"project_id\":2,\"challan_date\":\"2026-01-05\",\"work_description\":\"steel 1 day\",\"work_from_date\":\"2026-01-05\",\"work_to_date\":\"2026-01-05\",\"total_amount\":300,\"status\":\"pending\",\"created_by\":1}','::1','2026-01-05 14:42:39');
INSERT INTO audit_trail VALUES ('51','1','approve','challans','3','','','::1','2026-01-05 14:42:45');
INSERT INTO audit_trail VALUES ('52','1','create','payments','8','','{\"payment_type\":\"labour_payment\",\"reference_type\":\"challan\",\"reference_id\":3,\"party_id\":4,\"payment_date\":\"2026-01-05\",\"amount\":200,\"payment_mode\":\"cash\",\"reference_no\":\"\",\"remarks\":\"\",\"created_by\":1}','::1','2026-01-05 14:43:42');
INSERT INTO audit_trail VALUES ('53','1','approve','challans','2','','','::1','2026-01-05 14:49:37');
INSERT INTO audit_trail VALUES ('54','1','create','payments','9','','{\"payment_type\":\"customer_receipt\",\"reference_type\":\"booking\",\"reference_id\":4,\"party_id\":2,\"payment_date\":\"2026-01-05\",\"amount\":17800000,\"payment_mode\":\"cash\",\"reference_no\":\"\",\"remarks\":\"\",\"created_by\":1}','::1','2026-01-05 14:54:22');
INSERT INTO audit_trail VALUES ('55','1','update','settings','0','','{\"company_name\":\"Atri Group\",\"company_address\":\"Badlapur\",\"company_phone\":\"0000\",\"company_email\":\"\",\"gst_number\":\"hhvhjvvyug87t8iu63v\",\"financial_year_start\":\"April\"}','::1','2026-01-05 14:55:46');
INSERT INTO audit_trail VALUES ('56','1','logout','users','1','','','::1','2026-01-05 15:10:43');
INSERT INTO audit_trail VALUES ('57','1','login','users','1','','','::1','2026-01-05 15:10:51');
INSERT INTO audit_trail VALUES ('58','1','login','users','1','','','::1','2026-01-05 15:16:36');
INSERT INTO audit_trail VALUES ('59','1','login','users','1','','','::1','2026-01-07 10:21:46');
INSERT INTO audit_trail VALUES ('60','1','login','users','1','','','::1','2026-01-07 10:27:20');
INSERT INTO audit_trail VALUES ('61','1','create','payments','10','','{\"payment_type\":\"vendor_payment\",\"reference_type\":\"challan\",\"reference_id\":2,\"party_id\":3,\"payment_date\":\"2026-01-07\",\"amount\":30000,\"payment_mode\":\"cash\",\"reference_no\":\"\",\"remarks\":\"\",\"created_by\":1}','::1','2026-01-07 10:33:03');
INSERT INTO audit_trail VALUES ('62','1','create','challans','4','','{\"challan_no\":\"MAT\\/2026\\/0003\",\"challan_type\":\"material\",\"party_id\":3,\"project_id\":1,\"challan_date\":\"2026-01-07\",\"total_amount\":250000,\"status\":\"pending\",\"created_by\":1}','::1','2026-01-07 10:39:56');
INSERT INTO audit_trail VALUES ('63','1','approve','challans','4','','','::1','2026-01-07 10:39:58');
INSERT INTO audit_trail VALUES ('64','1','login','users','1','','','::1','2026-01-07 17:07:36');
INSERT INTO audit_trail VALUES ('65','1','create','challans','5','','{\"challan_no\":\"MAT\\/2026\\/0004\",\"challan_type\":\"material\",\"party_id\":3,\"project_id\":1,\"challan_date\":\"2026-01-07\",\"total_amount\":91380,\"status\":\"pending\",\"created_by\":1}','::1','2026-01-07 17:15:08');
INSERT INTO audit_trail VALUES ('66','1','create','parties','5','','{\"party_type\":\"vendor\",\"name\":\"Prerak Patel\",\"contact_person\":\"stone\",\"mobile\":\"888888888\",\"email\":\"\",\"address\":\"\",\"gst_number\":\"\"}','::1','2026-01-07 17:29:06');
INSERT INTO audit_trail VALUES ('67','1','approve','challans','5','','','::1','2026-01-07 17:30:44');
INSERT INTO audit_trail VALUES ('68','1','create','bookings','5','','{\"flat_id\":35,\"customer_id\":2,\"project_id\":2,\"agreement_value\":100000,\"booking_date\":\"2026-01-07\",\"status\":\"active\",\"created_by\":1}','::1','2026-01-07 19:29:11');
INSERT INTO audit_trail VALUES ('69','1','login','users','1','','','::1','2026-01-07 19:53:41');
INSERT INTO audit_trail VALUES ('70','1','login','users','1','','','::1','2026-01-07 22:39:20');
INSERT INTO audit_trail VALUES ('71','1','update','flats','1','','{\"flat_no\":\"A-101\",\"floor\":1,\"area_sqft\":2000,\"rate_per_sqft\":4,\"status\":\"available\"}','::1','2026-01-07 23:49:22');
INSERT INTO audit_trail VALUES ('72','1','update','flats','4','','{\"flat_no\":\"A-104\",\"floor\":1,\"area_sqft\":2000,\"rate_per_sqft\":4,\"status\":\"available\"}','::1','2026-01-07 23:49:41');
INSERT INTO audit_trail VALUES ('73','1','update','flats','18','','{\"flat_no\":\"f-102\",\"floor\":1,\"area_sqft\":400,\"rate_per_sqft\":250,\"status\":\"available\"}','::1','2026-01-07 23:49:48');
INSERT INTO audit_trail VALUES ('74','1','update','flats','35','','{\"flat_no\":\"f-503\",\"floor\":5,\"area_sqft\":400,\"rate_per_sqft\":250,\"status\":\"available\"}','::1','2026-01-07 23:49:57');
INSERT INTO audit_trail VALUES ('75','1','update','flats','24','','{\"flat_no\":\"f-204\",\"floor\":2,\"area_sqft\":400,\"rate_per_sqft\":250,\"status\":\"available\"}','::1','2026-01-07 23:50:04');
INSERT INTO audit_trail VALUES ('76','1','create','bookings','6','','{\"flat_id\":1,\"customer_id\":1,\"project_id\":1,\"agreement_value\":8000,\"booking_date\":\"2026-01-07\",\"status\":\"active\",\"created_by\":1}','::1','2026-01-07 23:50:55');
INSERT INTO audit_trail VALUES ('77','1','create','payments','11','','{\"payment_type\":\"customer_receipt\",\"reference_type\":\"booking\",\"reference_id\":6,\"party_id\":1,\"payment_date\":\"2026-01-07\",\"amount\":8000,\"payment_mode\":\"cash\",\"reference_no\":\"\",\"remarks\":\"\",\"created_by\":1}','::1','2026-01-07 23:51:06');
INSERT INTO audit_trail VALUES ('78','1','update','flats','1','','{\"flat_no\":\"A-101\",\"floor\":1,\"area_sqft\":2000,\"rate_per_sqft\":4,\"status\":\"available\"}','::1','2026-01-07 23:52:25');
INSERT INTO audit_trail VALUES ('79','1','login','users','1','','','::1','2026-01-08 23:33:02');
INSERT INTO audit_trail VALUES ('80','1','update','bookings','6','{\"status\":\"active\"}','{\"status\":\"cancelled\"}','::1','2026-01-08 23:45:10');
INSERT INTO audit_trail VALUES ('81','1','create','booking_cancellations','1','','{\"booking_id\":6,\"cancellation_date\":\"2026-01-08\",\"total_paid\":\"8000.00\",\"refund_amount\":7000,\"deduction_amount\":1000,\"deduction_reason\":\"other\",\"refund_mode\":\"cash\",\"refund_reference\":\"\",\"cancellation_reason\":\"Other\",\"remarks\":\"\",\"processed_by\":1}','::1','2026-01-08 23:45:10');
INSERT INTO audit_trail VALUES ('82','1','login','users','1','','','::1','2026-01-08 23:46:58');
INSERT INTO audit_trail VALUES ('83','1','update','bookings','5','{\"status\":\"active\"}','{\"status\":\"cancelled\"}','::1','2026-01-09 00:58:39');
INSERT INTO audit_trail VALUES ('84','1','create','booking_cancellations','2','','{\"booking_id\":5,\"cancellation_date\":\"2026-01-09\",\"total_paid\":\"0.00\",\"refund_amount\":0,\"deduction_amount\":0,\"deduction_reason\":\"wasted time\",\"refund_mode\":\"cash\",\"refund_reference\":\"\",\"cancellation_reason\":\"Financial Issues\",\"remarks\":\"\",\"processed_by\":1}','::1','2026-01-09 00:58:39');
INSERT INTO audit_trail VALUES ('85','1','logout','users','1','','','::1','2026-01-09 01:01:28');
INSERT INTO audit_trail VALUES ('86','1','login','users','1','','','::1','2026-01-09 01:01:33');
INSERT INTO audit_trail VALUES ('87','1','login','users','1','','','::1','2026-01-09 10:30:48');
INSERT INTO audit_trail VALUES ('88','1','login','users','1','','','::1','2026-01-09 11:16:14');
INSERT INTO audit_trail VALUES ('89','1','create','bookings','9','','{\"flat_id\":3,\"customer_id\":\"8\",\"project_id\":1,\"agreement_value\":500000,\"booking_date\":\"2026-01-09\",\"status\":\"active\",\"created_by\":1}','::1','2026-01-09 15:43:10');
INSERT INTO audit_trail VALUES ('90','1','update','bookings','9','{\"status\":\"active\"}','{\"status\":\"cancelled\"}','::1','2026-01-09 15:52:00');
INSERT INTO audit_trail VALUES ('91','1','create','booking_cancellations','3','','{\"booking_id\":9,\"cancellation_date\":\"2026-01-09\",\"total_paid\":\"0.00\",\"refund_amount\":0,\"deduction_amount\":0,\"deduction_reason\":\"Financial\",\"refund_mode\":\"cash\",\"refund_reference\":\"\",\"cancellation_reason\":\"Financial Issues\",\"remarks\":\"\",\"processed_by\":1}','::1','2026-01-09 15:52:00');
INSERT INTO audit_trail VALUES ('92','1','logout','users','1','','','::1','2026-01-09 16:20:15');
INSERT INTO audit_trail VALUES ('93','1','login','users','1','','','::1','2026-01-09 16:21:42');
INSERT INTO audit_trail VALUES ('94','1','logout','users','1','','','::1','2026-01-09 16:43:30');
INSERT INTO audit_trail VALUES ('95','1','login','users','1','','','::1','2026-01-09 16:52:01');
INSERT INTO audit_trail VALUES ('96','1','create','projects','5','','{\"project_name\":\"Test\",\"location\":\"Badlapur\",\"start_date\":\"2025-12-01\",\"expected_completion\":\"2026-12-10\",\"total_floors\":12,\"total_flats\":48,\"status\":\"active\",\"created_by\":1}','::1','2026-01-09 17:30:27');
INSERT INTO audit_trail VALUES ('97','1','login','users','1','','','::1','2026-01-09 18:05:53');
INSERT INTO audit_trail VALUES ('98','1','login','users','1','','','::1','2026-01-09 18:12:41');
INSERT INTO audit_trail VALUES ('99','1','create','flats','51','','{\"project_id\":5,\"flat_no\":\"A-101\",\"floor\":1,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('100','1','create','flats','52','','{\"project_id\":5,\"flat_no\":\"A-102\",\"floor\":1,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('101','1','create','flats','53','','{\"project_id\":5,\"flat_no\":\"A-103\",\"floor\":1,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('102','1','create','flats','54','','{\"project_id\":5,\"flat_no\":\"A-104\",\"floor\":1,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('103','1','create','flats','55','','{\"project_id\":5,\"flat_no\":\"A-201\",\"floor\":2,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('104','1','create','flats','56','','{\"project_id\":5,\"flat_no\":\"A-202\",\"floor\":2,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('105','1','create','flats','57','','{\"project_id\":5,\"flat_no\":\"A-203\",\"floor\":2,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('106','1','create','flats','58','','{\"project_id\":5,\"flat_no\":\"A-204\",\"floor\":2,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('107','1','create','flats','59','','{\"project_id\":5,\"flat_no\":\"A-301\",\"floor\":3,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('108','1','create','flats','60','','{\"project_id\":5,\"flat_no\":\"A-302\",\"floor\":3,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('109','1','create','flats','61','','{\"project_id\":5,\"flat_no\":\"A-303\",\"floor\":3,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('110','1','create','flats','62','','{\"project_id\":5,\"flat_no\":\"A-304\",\"floor\":3,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('111','1','create','flats','63','','{\"project_id\":5,\"flat_no\":\"A-401\",\"floor\":4,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('112','1','create','flats','64','','{\"project_id\":5,\"flat_no\":\"A-402\",\"floor\":4,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('113','1','create','flats','65','','{\"project_id\":5,\"flat_no\":\"A-403\",\"floor\":4,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('114','1','create','flats','66','','{\"project_id\":5,\"flat_no\":\"A-404\",\"floor\":4,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('115','1','create','flats','67','','{\"project_id\":5,\"flat_no\":\"A-501\",\"floor\":5,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('116','1','create','flats','68','','{\"project_id\":5,\"flat_no\":\"A-502\",\"floor\":5,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('117','1','create','flats','69','','{\"project_id\":5,\"flat_no\":\"A-503\",\"floor\":5,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('118','1','create','flats','70','','{\"project_id\":5,\"flat_no\":\"A-504\",\"floor\":5,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('119','1','create','flats','71','','{\"project_id\":5,\"flat_no\":\"A-601\",\"floor\":6,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('120','1','create','flats','72','','{\"project_id\":5,\"flat_no\":\"A-602\",\"floor\":6,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('121','1','create','flats','73','','{\"project_id\":5,\"flat_no\":\"A-603\",\"floor\":6,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('122','1','create','flats','74','','{\"project_id\":5,\"flat_no\":\"A-604\",\"floor\":6,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('123','1','create','flats','75','','{\"project_id\":5,\"flat_no\":\"A-701\",\"floor\":7,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('124','1','create','flats','76','','{\"project_id\":5,\"flat_no\":\"A-702\",\"floor\":7,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('125','1','create','flats','77','','{\"project_id\":5,\"flat_no\":\"A-703\",\"floor\":7,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('126','1','create','flats','78','','{\"project_id\":5,\"flat_no\":\"A-704\",\"floor\":7,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('127','1','create','flats','79','','{\"project_id\":5,\"flat_no\":\"A-801\",\"floor\":8,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('128','1','create','flats','80','','{\"project_id\":5,\"flat_no\":\"A-802\",\"floor\":8,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('129','1','create','flats','81','','{\"project_id\":5,\"flat_no\":\"A-803\",\"floor\":8,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('130','1','create','flats','82','','{\"project_id\":5,\"flat_no\":\"A-804\",\"floor\":8,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('131','1','create','flats','83','','{\"project_id\":5,\"flat_no\":\"A-901\",\"floor\":9,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('132','1','create','flats','84','','{\"project_id\":5,\"flat_no\":\"A-902\",\"floor\":9,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('133','1','create','flats','85','','{\"project_id\":5,\"flat_no\":\"A-903\",\"floor\":9,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('134','1','create','flats','86','','{\"project_id\":5,\"flat_no\":\"A-904\",\"floor\":9,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('135','1','create','flats','87','','{\"project_id\":5,\"flat_no\":\"A-1001\",\"floor\":10,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('136','1','create','flats','88','','{\"project_id\":5,\"flat_no\":\"A-1002\",\"floor\":10,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('137','1','create','flats','89','','{\"project_id\":5,\"flat_no\":\"A-1003\",\"floor\":10,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('138','1','create','flats','90','','{\"project_id\":5,\"flat_no\":\"A-1004\",\"floor\":10,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('139','1','create','flats','91','','{\"project_id\":5,\"flat_no\":\"A-1101\",\"floor\":11,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('140','1','create','flats','92','','{\"project_id\":5,\"flat_no\":\"A-1102\",\"floor\":11,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('141','1','create','flats','93','','{\"project_id\":5,\"flat_no\":\"A-1103\",\"floor\":11,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('142','1','create','flats','94','','{\"project_id\":5,\"flat_no\":\"A-1104\",\"floor\":11,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('143','1','create','flats','95','','{\"project_id\":5,\"flat_no\":\"A-1201\",\"floor\":12,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('144','1','create','flats','96','','{\"project_id\":5,\"flat_no\":\"A-1202\",\"floor\":12,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('145','1','create','flats','97','','{\"project_id\":5,\"flat_no\":\"A-1203\",\"floor\":12,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('146','1','create','flats','98','','{\"project_id\":5,\"flat_no\":\"A-1204\",\"floor\":12,\"area_sqft\":4000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-09 18:14:51');
INSERT INTO audit_trail VALUES ('147','1','create','bookings','10','','{\"flat_id\":87,\"customer_id\":\"9\",\"project_id\":5,\"agreement_value\":5000000,\"booking_date\":\"2026-01-09\",\"status\":\"active\",\"created_by\":1}','::1','2026-01-09 18:16:07');
INSERT INTO audit_trail VALUES ('148','1','create','payments','17','','{\"payment_type\":\"customer_receipt\",\"reference_type\":\"booking\",\"reference_id\":10,\"party_id\":9,\"payment_date\":\"2026-01-09\",\"amount\":4000000,\"payment_mode\":\"bank\",\"reference_no\":\"\",\"remarks\":\"\",\"created_by\":1}','::1','2026-01-09 18:16:46');
INSERT INTO audit_trail VALUES ('149','1','update','bookings','10','{\"status\":\"active\"}','{\"status\":\"cancelled\"}','::1','2026-01-09 18:26:18');
INSERT INTO audit_trail VALUES ('150','1','create','booking_cancellations','4','','{\"booking_id\":10,\"cancellation_date\":\"2026-01-09\",\"total_paid\":\"4000000.00\",\"refund_amount\":4000000,\"deduction_amount\":0,\"deduction_reason\":\"None\",\"refund_mode\":\"bank\",\"refund_reference\":\"\",\"cancellation_reason\":\"Customer Request\",\"remarks\":\"\",\"processed_by\":1}','::1','2026-01-09 18:26:18');
INSERT INTO audit_trail VALUES ('151','1','login','users','1','','','::1','2026-01-09 19:06:48');
INSERT INTO audit_trail VALUES ('152','1','login','users','1','','','::1','2026-01-09 19:12:56');
INSERT INTO audit_trail VALUES ('153','1','login','users','1','','','::1','2026-01-09 19:35:31');
INSERT INTO audit_trail VALUES ('154','1','login','users','1','','','::1','2026-01-09 19:42:39');
INSERT INTO audit_trail VALUES ('155','1','login','users','1','','','::1','2026-01-09 19:54:01');
INSERT INTO audit_trail VALUES ('156','1','login','users','1','','','::1','2026-01-09 19:55:57');
INSERT INTO audit_trail VALUES ('157','1','logout','users','1','','','::1','2026-01-09 19:57:07');
INSERT INTO audit_trail VALUES ('158','1','login','users','1','','','::1','2026-01-09 19:59:33');
INSERT INTO audit_trail VALUES ('159','1','login','users','1','','','::1','2026-01-10 07:11:51');
INSERT INTO audit_trail VALUES ('160','1','login','users','1','','','::1','2026-01-10 07:18:59');
INSERT INTO audit_trail VALUES ('161','1','logout','users','1','','','::1','2026-01-10 07:25:23');
INSERT INTO audit_trail VALUES ('162','1','login','users','1','','','::1','2026-01-10 07:30:34');
INSERT INTO audit_trail VALUES ('163','1','logout','users','1','','','::1','2026-01-10 07:53:04');
INSERT INTO audit_trail VALUES ('164','1','login','users','1','','','::1','2026-01-10 07:54:01');
INSERT INTO audit_trail VALUES ('165','1','logout','users','1','','','::1','2026-01-10 07:57:02');
INSERT INTO audit_trail VALUES ('166','1','login','users','1','','','::1','2026-01-10 20:17:08');
INSERT INTO audit_trail VALUES ('167','1','login','users','1','','','::1','2026-01-10 20:38:44');
INSERT INTO audit_trail VALUES ('168','1','login','users','1','','','::1','2026-01-10 20:51:35');
INSERT INTO audit_trail VALUES ('169','1','create','bookings','11','','{\"flat_id\":87,\"customer_id\":\"10\",\"project_id\":5,\"agreement_value\":40000000,\"booking_date\":\"2026-01-10\",\"status\":\"active\",\"created_by\":1}','::1','2026-01-10 20:55:50');
INSERT INTO audit_trail VALUES ('170','1','logout','users','1','','','::1','2026-01-10 20:59:28');
INSERT INTO audit_trail VALUES ('171','1','login','users','1','','','::1','2026-01-10 21:07:34');
INSERT INTO audit_trail VALUES ('172','1','login','users','1','','','::1','2026-01-10 21:19:26');
INSERT INTO audit_trail VALUES ('173','1','logout','users','1','','','::1','2026-01-10 21:33:56');
INSERT INTO audit_trail VALUES ('174','1','login','users','1','','','::1','2026-01-10 21:34:07');
INSERT INTO audit_trail VALUES ('175','1','logout','users','1','','','::1','2026-01-10 22:06:38');
INSERT INTO audit_trail VALUES ('176','1','login','users','1','','','::1','2026-01-10 22:07:02');
INSERT INTO audit_trail VALUES ('177','1','logout','users','1','','','::1','2026-01-10 22:31:46');
INSERT INTO audit_trail VALUES ('178','1','login','users','1','','','::1','2026-01-11 07:40:52');
INSERT INTO audit_trail VALUES ('179','1','create','payments','19','','{\"payment_type\":\"customer_receipt\",\"reference_type\":\"booking\",\"reference_id\":11,\"party_id\":10,\"payment_date\":\"2026-01-11\",\"amount\":10000000,\"payment_mode\":\"bank\",\"reference_no\":\"\",\"remarks\":\"\",\"created_by\":1}','::1','2026-01-11 08:08:10');
INSERT INTO audit_trail VALUES ('180','1','update','bookings','11','{\"status\":\"active\"}','{\"status\":\"cancelled\"}','::1','2026-01-11 08:13:18');
INSERT INTO audit_trail VALUES ('181','1','create','booking_cancellations','5','','{\"booking_id\":11,\"cancellation_date\":\"2026-01-11\",\"total_paid\":\"10000000.00\",\"refund_amount\":9900000,\"deduction_amount\":100000,\"deduction_reason\":\"Financial\",\"refund_mode\":\"bank\",\"refund_reference\":\"\",\"cancellation_reason\":\"Customer Request\",\"remarks\":\"\",\"processed_by\":1}','::1','2026-01-11 08:13:18');
INSERT INTO audit_trail VALUES ('182','1','create','bookings','12','','{\"flat_id\":87,\"customer_id\":\"11\",\"project_id\":5,\"agreement_value\":100000,\"booking_date\":\"2026-01-11\",\"status\":\"active\",\"created_by\":1}','::1','2026-01-11 09:20:01');
INSERT INTO audit_trail VALUES ('183','1','create','challans','6','','{\"challan_no\":\"MAT\\/2026\\/0001\",\"challan_type\":\"material\",\"party_id\":\"12\",\"project_id\":5,\"challan_date\":\"2026-01-11\",\"vehicle_no\":\"MH12Ab1234\",\"total_amount\":1.932,\"status\":\"pending\",\"created_by\":1}','::1','2026-01-11 09:35:59');
INSERT INTO audit_trail VALUES ('184','1','approve','challans','6','','','::1','2026-01-11 09:44:15');
INSERT INTO audit_trail VALUES ('185','1','create','challans','7','','{\"challan_no\":\"LAB\\/2026\\/0001\",\"challan_type\":\"labour\",\"party_id\":\"13\",\"project_id\":5,\"challan_date\":\"2026-01-11\",\"work_description\":\"Cement Labour\",\"work_from_date\":\"2026-01-01\",\"work_to_date\":\"2027-01-01\",\"total_amount\":10000,\"status\":\"pending\",\"created_by\":1}','::1','2026-01-11 10:00:19');
INSERT INTO audit_trail VALUES ('186','1','approve','challans','7','','','::1','2026-01-11 10:00:42');
INSERT INTO audit_trail VALUES ('187','1','login','users','1','','','::1','2026-01-12 08:33:12');
INSERT INTO audit_trail VALUES ('188','1','create','flats','99','','{\"project_id\":5,\"flat_no\":\"A-1301\",\"floor\":12,\"area_sqft\":1000,\"rate_per_sqft\":10000,\"status\":\"available\"}','::1','2026-01-12 09:23:49');
INSERT INTO audit_trail VALUES ('189','1','login','users','1','','','::1','2026-01-12 09:31:18');
INSERT INTO audit_trail VALUES ('190','1','delete','flats','99','','','::1','2026-01-12 09:37:19');
INSERT INTO audit_trail VALUES ('191','1','login','users','1','','','::1','2026-01-12 10:00:35');
INSERT INTO audit_trail VALUES ('192','1','create','parties','14','','{\"party_type\":\"labour\",\"name\":\"Arya\",\"contact_person\":\"Manoj Patel\",\"mobile\":\"1254785203\",\"email\":\"\",\"address\":\"Mumbai\",\"gst_number\":\"\"}','::1','2026-01-12 11:35:43');
INSERT INTO audit_trail VALUES ('193','1','delete','parties','14','','','::1','2026-01-12 11:40:35');
INSERT INTO audit_trail VALUES ('194','1','create','payments','21','','{\"payment_type\":\"customer_receipt\",\"reference_type\":\"booking\",\"reference_id\":12,\"party_id\":11,\"payment_date\":\"2026-01-12\",\"amount\":50000,\"payment_mode\":\"upi\",\"reference_no\":\"\",\"remarks\":\"\",\"created_by\":1}','::1','2026-01-12 13:12:52');
INSERT INTO audit_trail VALUES ('195','1','logout','users','1','','','::1','2026-01-12 17:57:17');
INSERT INTO audit_trail VALUES ('196','1','login','users','1','','','::1','2026-01-12 17:57:24');
INSERT INTO audit_trail VALUES ('197','1','logout','users','1','','','::1','2026-01-12 19:52:01');
INSERT INTO audit_trail VALUES ('198','1','login','users','1','','','::1','2026-01-12 19:52:13');
INSERT INTO audit_trail VALUES ('199','1','logout','users','1','','','::1','2026-01-12 19:52:45');
INSERT INTO audit_trail VALUES ('200','1','login','users','1','','','::1','2026-01-13 10:37:13');
INSERT INTO audit_trail VALUES ('201','1','logout','users','1','','','::1','2026-01-13 10:41:35');
INSERT INTO audit_trail VALUES ('202','1','login','users','1','','','::1','2026-01-13 10:44:51');
INSERT INTO audit_trail VALUES ('203','1','login','users','1','','','::1','2026-01-13 11:10:26');
INSERT INTO audit_trail VALUES ('204','1','logout','users','1','','','::1','2026-01-13 11:12:36');
INSERT INTO audit_trail VALUES ('205','1','login','users','1','','','::1','2026-01-13 11:20:13');
INSERT INTO audit_trail VALUES ('206','1','login','users','1','','','::1','2026-01-14 16:44:20');
INSERT INTO audit_trail VALUES ('207','1','logout','users','1','','','::1','2026-01-14 17:38:17');
INSERT INTO audit_trail VALUES ('208','1','login','users','1','','','::1','2026-01-15 11:03:55');
INSERT INTO audit_trail VALUES ('209','1','logout','users','1','','','::1','2026-01-16 11:20:02');
INSERT INTO audit_trail VALUES ('210','1','login','users','1','','','::1','2026-01-16 11:20:43');
INSERT INTO audit_trail VALUES ('211','1','logout','users','1','','','::1','2026-01-16 14:39:54');
INSERT INTO audit_trail VALUES ('212','1','login','users','1','','','::1','2026-01-16 14:40:05');
INSERT INTO audit_trail VALUES ('213','1','','flats','87','{\"status\":\"booked\"}','{\"status\":\"sold\"}','::1','2026-01-16 15:00:25');
INSERT INTO audit_trail VALUES ('214','1','create','payments','22','','{\"payment_type\":\"customer_receipt\",\"reference_type\":\"booking\",\"reference_id\":12,\"party_id\":11,\"payment_date\":\"2026-01-16\",\"amount\":50000,\"payment_mode\":\"upi\",\"reference_no\":\"\",\"remarks\":\"\",\"created_by\":1}','::1','2026-01-16 15:00:25');
INSERT INTO audit_trail VALUES ('215','1','login','users','1','','','::1','2026-01-16 16:01:48');
INSERT INTO audit_trail VALUES ('216','1','create','projects','6','','{\"project_name\":\"Skyline\",\"location\":\"Kharghar\",\"start_date\":\"2026-01-31\",\"expected_completion\":\"2027-01-31\",\"total_floors\":50,\"total_flats\":200,\"status\":\"active\",\"created_by\":1}','::1','2026-01-16 16:29:26');
INSERT INTO audit_trail VALUES ('217','1','logout','users','1','','','::1','2026-01-16 18:20:22');
INSERT INTO audit_trail VALUES ('218','1','login','users','1','','','::1','2026-01-16 18:20:30');
INSERT INTO audit_trail VALUES ('219','1','login','users','1','','','::1','2026-01-16 18:42:00');
INSERT INTO audit_trail VALUES ('220','1','delete','projects','6','','','::1','2026-01-16 18:43:58');
INSERT INTO audit_trail VALUES ('221','1','login','users','1','','','::1','2026-01-16 19:12:45');
INSERT INTO audit_trail VALUES ('222','1','login','users','1','','','::1','2026-01-16 19:15:56');
INSERT INTO audit_trail VALUES ('223','1','login','users','1','','','::1','2026-01-16 19:23:35');
INSERT INTO audit_trail VALUES ('224','1','logout','users','1','','','::1','2026-01-16 19:25:03');
INSERT INTO audit_trail VALUES ('225','1','login','users','1','','','::1','2026-01-16 19:25:12');
INSERT INTO audit_trail VALUES ('226','1','update','settings','0','','{\"company_name\":\"Atri Group\",\"company_address\":\"Badlapur\",\"company_phone\":\"0000\",\"company_email\":\"\",\"gst_number\":\"hhvhjvvyug87t8iu63v\",\"financial_year_start\":\"April\",\"company_logo\":\"uploads\\/settings\\/logo_1768576083.jpeg\"}','::1','2026-01-16 20:38:03');
INSERT INTO audit_trail VALUES ('227','1','create','projects','7','','{\"project_name\":\"Skyline\",\"location\":\"Kharghar\",\"start_date\":\"2026-01-31\",\"expected_completion\":\"2027-01-31\",\"total_floors\":12,\"total_flats\":48,\"status\":\"active\",\"created_by\":1}','::1','2026-01-16 20:43:21');
INSERT INTO audit_trail VALUES ('228','1','delete','projects','7','','','::1','2026-01-16 20:43:34');
INSERT INTO audit_trail VALUES ('229','1','login','users','1','','','::1','2026-01-16 21:19:53');
INSERT INTO audit_trail VALUES ('230','1','login','users','1','','','::1','2026-01-17 08:55:13');
INSERT INTO audit_trail VALUES ('231','1','logout','users','1','','','::1','2026-01-17 10:10:27');
INSERT INTO audit_trail VALUES ('232','1','login','users','1','','','::1','2026-01-17 12:16:16');


CREATE TABLE `booking_cancellations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `cancellation_date` date NOT NULL,
  `total_paid` decimal(12,2) NOT NULL,
  `refund_amount` decimal(12,2) NOT NULL,
  `deduction_amount` decimal(12,2) NOT NULL,
  `deduction_reason` varchar(255) DEFAULT NULL,
  `refund_mode` enum('cash','bank','upi','cheque') NOT NULL,
  `refund_reference` varchar(100) DEFAULT NULL,
  `cancellation_reason` varchar(255) NOT NULL,
  `remarks` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_booking` (`booking_id`),
  KEY `idx_date` (`cancellation_date`),
  KEY `processed_by` (`processed_by`),
  CONSTRAINT `booking_cancellations_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `booking_cancellations_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO booking_cancellations VALUES ('4','10','2026-01-09','4000000.00','4000000.00','0.00','None','bank','','Customer Request','','1','2026-01-09 18:26:18','2026-01-09 18:26:18');
INSERT INTO booking_cancellations VALUES ('5','11','2026-01-11','10000000.00','9900000.00','100000.00','Financial','bank','','Customer Request','','1','2026-01-11 08:13:18','2026-01-11 08:13:18');


CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `flat_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `agreement_value` decimal(12,2) NOT NULL,
  `booking_date` date NOT NULL,
  `total_received` decimal(12,2) DEFAULT 0.00,
  `total_pending` decimal(12,2) GENERATED ALWAYS AS (`agreement_value` - `total_received`) STORED,
  `status` enum('active','cancelled','completed') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `flat_id` (`flat_id`),
  KEY `customer_id` (`customer_id`),
  KEY `project_id` (`project_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`flat_id`) REFERENCES `flats` (`id`),
  CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `parties` (`id`),
  CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  CONSTRAINT `bookings_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO bookings VALUES ('10','87','9','5','5000000.00','2026-01-09','4000000.00','1000000.00','cancelled','1','2026-01-09 18:16:07','2026-01-09 18:26:18');
INSERT INTO bookings VALUES ('11','87','10','5','40000000.00','2026-01-10','10000000.00','30000000.00','cancelled','1','2026-01-10 20:55:50','2026-01-11 08:13:18');
INSERT INTO bookings VALUES ('12','87','11','5','100000.00','2026-01-11','100000.00','0.00','active','1','2026-01-11 09:20:01','2026-01-16 15:00:25');


CREATE TABLE `challan_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `challan_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `rate` decimal(10,2) NOT NULL,
  `total_amount` decimal(12,2) GENERATED ALWAYS AS (`quantity` * `rate`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `challan_id` (`challan_id`),
  KEY `material_id` (`material_id`),
  CONSTRAINT `challan_items_ibfk_1` FOREIGN KEY (`challan_id`) REFERENCES `challans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `challan_items_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO challan_items VALUES ('6','6','3','1.61','1.20','1.93','2026-01-11 09:35:59');


CREATE TABLE `challans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `challan_no` varchar(50) NOT NULL,
  `challan_type` enum('material','labour','customer') NOT NULL,
  `party_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `challan_date` date NOT NULL,
  `vehicle_no` varchar(50) DEFAULT NULL,
  `work_description` text DEFAULT NULL,
  `work_from_date` date DEFAULT NULL,
  `work_to_date` date DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `pending_amount` decimal(12,2) GENERATED ALWAYS AS (`total_amount` - `paid_amount`) STORED,
  `status` enum('pending','partial','paid','approved') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `challan_no` (`challan_no`),
  KEY `idx_challan_no` (`challan_no`),
  KEY `idx_type` (`challan_type`),
  KEY `idx_status` (`status`),
  KEY `party_id` (`party_id`),
  KEY `project_id` (`project_id`),
  KEY `approved_by` (`approved_by`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `challans_ibfk_1` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`),
  CONSTRAINT `challans_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  CONSTRAINT `challans_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `challans_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO challans VALUES ('6','MAT/2026/0001','material','12','5','2026-01-11','MH12Ab1234','','','','1.93','0.00','1.93','approved','1','2026-01-11 09:44:15','1','2026-01-11 09:35:59','2026-01-11 09:44:15');
INSERT INTO challans VALUES ('7','LAB/2026/0001','labour','13','5','2026-01-11','','Cement Labour','2026-01-01','2027-01-01','10000.00','0.00','10000.00','approved','1','2026-01-11 10:00:42','1','2026-01-11 10:00:19','2026-01-11 10:00:42');


CREATE TABLE `financial_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_type` enum('income','expenditure') NOT NULL,
  `category` varchar(100) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type` (`transaction_type`),
  KEY `idx_category` (`category`),
  KEY `idx_date` (`transaction_date`),
  KEY `idx_project` (`project_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `financial_transactions_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `financial_transactions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO financial_transactions VALUES ('2','income','cancellation_charges','booking_cancellation','5','5','2026-01-11','100000.00','Cancellation charges - Booking #11 - Financial','1','2026-01-11 08:13:18','2026-01-11 08:13:18');


CREATE TABLE `flats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `block` varchar(50) DEFAULT NULL,
  `flat_no` varchar(50) NOT NULL,
  `floor` int(11) NOT NULL,
  `area_sqft` decimal(10,2) NOT NULL,
  `bhk` varchar(20) DEFAULT NULL,
  `rate_per_sqft` decimal(10,2) NOT NULL,
  `total_value` decimal(12,2) GENERATED ALWAYS AS (`area_sqft` * `rate_per_sqft`) STORED,
  `status` enum('available','booked','sold') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_flat` (`project_id`,`flat_no`),
  KEY `idx_status` (`status`),
  CONSTRAINT `flats_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO flats VALUES ('51','5','','A-101','1','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('52','5','','A-102','1','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('53','5','','A-103','1','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('54','5','','A-104','1','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('55','5','','A-201','2','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('56','5','','A-202','2','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('57','5','','A-203','2','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('58','5','','A-204','2','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('59','5','','A-301','3','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('60','5','','A-302','3','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('61','5','','A-303','3','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('62','5','','A-304','3','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('63','5','','A-401','4','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('64','5','','A-402','4','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('65','5','','A-403','4','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('66','5','','A-404','4','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('67','5','','A-501','5','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('68','5','','A-502','5','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('69','5','','A-503','5','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('70','5','','A-504','5','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('71','5','','A-601','6','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('72','5','','A-602','6','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('73','5','','A-603','6','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('74','5','','A-604','6','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('75','5','','A-701','7','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('76','5','','A-702','7','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('77','5','','A-703','7','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('78','5','','A-704','7','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('79','5','','A-801','8','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('80','5','','A-802','8','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('81','5','','A-803','8','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('82','5','','A-804','8','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('83','5','','A-901','9','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('84','5','','A-902','9','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('85','5','','A-903','9','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('86','5','','A-904','9','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('87','5','','A-1001','10','4000.00','','10000.00','40000000.00','sold','2026-01-09 18:14:51','2026-01-16 15:00:25');
INSERT INTO flats VALUES ('88','5','','A-1002','10','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('89','5','','A-1003','10','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('90','5','','A-1004','10','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('91','5','','A-1101','11','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('92','5','','A-1102','11','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('93','5','','A-1103','11','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('94','5','','A-1104','11','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('95','5','','A-1201','12','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('96','5','','A-1202','12','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('97','5','','A-1203','12','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');
INSERT INTO flats VALUES ('98','5','','A-1204','12','4000.00','','10000.00','40000000.00','available','2026-01-09 18:14:51','2026-01-09 18:14:51');


CREATE TABLE `material_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `usage_date` date NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `material_id` (`material_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `material_usage_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  CONSTRAINT `material_usage_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`),
  CONSTRAINT `material_usage_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_name` varchar(200) NOT NULL,
  `unit` enum('kg','ton','bag','cft','sqft','nos','ltr','brass','bundle') NOT NULL,
  `default_rate` decimal(10,2) DEFAULT NULL,
  `current_stock` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_name` (`material_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO materials VALUES ('3','cement','kg','1.20','1.61','2026-01-11 09:35:59','2026-01-11 09:35:59');


CREATE TABLE `parties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `party_type` enum('customer','vendor','labour') NOT NULL,
  `name` varchar(200) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `gst_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_party_type` (`party_type`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO parties VALUES ('9','customer','Bhishmang','','1234567890','','Mumbai','','2026-01-09 18:16:07','2026-01-09 18:16:07');
INSERT INTO parties VALUES ('10','customer','Test','','1234567890','','Kamothe','','2026-01-10 20:55:50','2026-01-10 20:55:50');
INSERT INTO parties VALUES ('11','customer','Vaidik','','1234567890','','Mumbai','','2026-01-11 09:20:01','2026-01-11 09:20:01');
INSERT INTO parties VALUES ('12','vendor','Abc Cement','','1234567890','','Gujarat','GSTIN136548556','2026-01-11 09:35:59','2026-01-11 09:35:59');
INSERT INTO parties VALUES ('13','labour','Labour','Etc','1234567890','','','','2026-01-11 10:00:19','2026-01-11 10:00:19');


CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_type` enum('customer_receipt','vendor_payment','labour_payment','customer_refund') NOT NULL,
  `reference_type` enum('booking','challan','booking_cancellation') NOT NULL,
  `reference_id` int(11) NOT NULL,
  `party_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_mode` enum('cash','bank','upi','cheque') NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payment_type` (`payment_type`),
  KEY `idx_reference` (`reference_type`,`reference_id`),
  KEY `party_id` (`party_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`),
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO payments VALUES ('17','customer_receipt','booking','10','9','2026-01-09','4000000.00','bank','','','1','2026-01-09 18:16:46','2026-01-09 18:16:46');
INSERT INTO payments VALUES ('18','customer_refund','booking_cancellation','4','9','2026-01-09','4000000.00','bank','','Refund for cancelled booking #10','1','2026-01-09 18:26:18','2026-01-09 18:26:18');
INSERT INTO payments VALUES ('19','customer_receipt','booking','11','10','2026-01-11','10000000.00','bank','','','1','2026-01-11 08:08:10','2026-01-11 08:08:10');
INSERT INTO payments VALUES ('20','customer_refund','booking_cancellation','5','10','2026-01-11','9900000.00','bank','','Refund for cancelled booking #11','1','2026-01-11 08:13:18','2026-01-11 08:13:18');
INSERT INTO payments VALUES ('21','customer_receipt','booking','12','11','2026-01-12','50000.00','upi','','','1','2026-01-12 13:12:52','2026-01-12 13:12:52');
INSERT INTO payments VALUES ('22','customer_receipt','booking','12','11','2026-01-16','50000.00','upi','','','1','2026-01-16 15:00:25','2026-01-16 15:00:25');


CREATE TABLE `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_name` varchar(200) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `expected_completion` date DEFAULT NULL,
  `total_floors` int(11) DEFAULT 0,
  `total_flats` int(11) DEFAULT 0,
  `status` enum('active','completed','on_hold') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO projects VALUES ('5','Test','Badlapur','2025-12-01','2026-12-10','12','48','active','1','2026-01-09 17:30:27','2026-01-09 17:30:27');


CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings VALUES ('1','company_name','Atri Group','2026-01-05 14:55:46');
INSERT INTO settings VALUES ('2','financial_year','2025-2026','2026-01-03 00:26:56');
INSERT INTO settings VALUES ('3','gst_number','hhvhjvvyug87t8iu63v','2026-01-05 14:55:46');
INSERT INTO settings VALUES ('4','address','','2026-01-03 00:26:56');
INSERT INTO settings VALUES ('5','phone','','2026-01-03 00:26:56');
INSERT INTO settings VALUES ('6','email','','2026-01-03 00:26:56');
INSERT INTO settings VALUES ('7','company_address','Badlapur','2026-01-05 14:55:46');
INSERT INTO settings VALUES ('8','company_phone','0000','2026-01-05 14:55:46');
INSERT INTO settings VALUES ('9','company_email','','2026-01-03 00:28:38');
INSERT INTO settings VALUES ('10','financial_year_start','April','2026-01-03 00:28:38');
INSERT INTO settings VALUES ('11','company_logo','uploads/settings/logo_1768576083.jpeg','2026-01-16 20:38:03');


CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','accountant','project_manager') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users VALUES ('1','admin','$2y$10$7J9.u9HEfVy8X4tDzpwE/uf8M28Oi/TZgMVAX9Go/N4lxoRm3YRya','System Administrator','admin@builderz.local','admin','active','2026-01-03 00:26:56','2026-01-03 00:26:56');
INSERT INTO users VALUES ('3','vaidik','$2y$10$zhMhCWOzqzhw/O6pL2lFVe10VIBUBv9jgtCvlN52yVToRx5lz3HIu','Vaidik Patel','something@gmail.com','project_manager','active','2026-01-05 14:36:11','2026-01-05 14:36:11');
INSERT INTO users VALUES ('4','prerak','$2y$10$djtL1Ig1TNXvV7HRRfwsz.NXlAfg1scQQ9Et34IbsUU9bE1xH3VUC','prerak patel','prerak@gmail.com','accountant','active','2026-01-05 14:39:24','2026-01-05 14:39:24');
