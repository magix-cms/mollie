CREATE TABLE IF NOT EXISTS `mc_mollie` (
  `id_mollie` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `apikey` varchar(150) NOT NULL,
  PRIMARY KEY (`id_mollie`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `mc_mollie_history` (
  `id_mollie_h` int(7) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_h` varchar(50) NOT NULL,
  `status_h` varchar(30) NOT NULL,
  `date_register` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_mollie_h`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;