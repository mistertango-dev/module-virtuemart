DROP TABLE IF EXISTS `#__mistertengo_payment`;
CREATE TABLE IF NOT EXISTS `#__mistertengo_payment` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `orderid` varchar(10) NOT NULL,
  `amount` float NOT NULL,
  `status` varchar(10) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `invoice` text NOT NULL,
  `hash` text NOT NULL,
  `payament_status` varchar(10) NOT NULL,
  `Payment_type` varchar(20) NOT NULL,
  `ws_id` varchar(200) NOT NULL,
  `type` varchar(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
