-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mer. 16 avr. 2025 à 14:28
-- Version du serveur : 8.3.0
-- Version de PHP : 8.2.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `mediatek86`
--

DELIMITER $$
--
-- Procédures
--
DROP PROCEDURE IF EXISTS `creerAbonnement`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `creerAbonnement` (IN `p_idCommande` VARCHAR(5), IN `p_idRevue` VARCHAR(10), IN `p_dateCommande` DATE, IN `p_dateFinAbonnement` DATE, IN `p_montant` DOUBLE, IN `p_nbExemplaire` INT)   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
    END;

    START TRANSACTION;

    -- Insérer dans commande.
    INSERT INTO commande(id, dateCommande, montant)
    VALUES (p_idCommande, p_dateCommande, p_montant);

    -- Vérifier si la revue existe dans livres_dvd pour l'ajouter si ce n'est pas le cas.
    IF NOT EXISTS (SELECT 1 FROM livres_dvd WHERE id = p_idRevue) THEN
        INSERT INTO livres_dvd(id) VALUES (p_idRevue);
    END IF;

    -- Insérer dans commandedocument.
    INSERT INTO commandedocument(id, nbExemplaire, idLivreDvd, idSuivi)
    VALUES (p_idCommande, p_nbExemplaire, p_idRevue, '00001');

    -- Insérer dans abonnement.
    INSERT INTO abonnement(id, idRevue, dateFinAbonnement)
    VALUES (p_idCommande, p_idRevue, p_dateFinAbonnement);

    COMMIT;
END$$

DROP PROCEDURE IF EXISTS `supprimerAbonnement`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `supprimerAbonnement` (IN `p_id` VARCHAR(5))   BEGIN
    DECLARE cDate DATE;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Erreur lors de la suppression de l\'abonnement.';
    END;

    -- Récupérer la date de commande liée à l’abonnement.
    SELECT dateCommande INTO cDate FROM commande WHERE id = p_id;

    -- Vérification de parution (exemplaire) liée à la revue durant l’abonnement.
    -- Si une parution existe, on interdit la suppression.
    IF EXISTS (
        SELECT 1
        FROM exemplaire e
        JOIN abonnement a ON a.idRevue = e.id
        WHERE a.id = p_id
          AND e.dateAchat BETWEEN cDate AND a.dateFinAbonnement
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Suppression interdite : une parution a été enregistrée durant la période de l\'abonnement.';
    END IF;

    START TRANSACTION;

    DELETE FROM abonnement WHERE id = p_id;
    DELETE FROM commandedocument WHERE id = p_id;
    DELETE FROM commande WHERE id = p_id;

    COMMIT;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `abonnement`
--

DROP TABLE IF EXISTS `abonnement`;
CREATE TABLE IF NOT EXISTS `abonnement` (
  `id` varchar(5) NOT NULL,
  `dateFinAbonnement` date DEFAULT NULL,
  `idRevue` varchar(10) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idRevue` (`idRevue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `abonnement`
--

INSERT INTO `abonnement` (`id`, `dateFinAbonnement`, `idRevue`) VALUES
('00026', '2026-04-10', '10007'),
('00027', '2025-04-30', '10008');

--
-- Déclencheurs `abonnement`
--
DROP TRIGGER IF EXISTS `abonnement_before_insert`;
DELIMITER $$
CREATE TRIGGER `abonnement_before_insert` BEFORE INSERT ON `abonnement` FOR EACH ROW BEGIN
    DECLARE dateCommande DATE;

    SELECT dateCommande INTO dateCommande
    FROM commande
    WHERE id = NEW.id;

    -- On vérifie s'il existe un chevauchement avec un autre abonnement.
    IF EXISTS (
        SELECT 1
        FROM abonnement a
        JOIN commande c ON a.id = c.id
        WHERE a.idRevue = NEW.idRevue
        AND a.id != NEW.id
        AND (
            dateCommande BETWEEN c.dateCommande AND a.dateFinAbonnement
            OR NEW.dateFinAbonnement BETWEEN c.dateCommande AND a.dateFinAbonnement
            OR c.dateCommande BETWEEN dateCommande AND NEW.dateFinAbonnement
        )
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Un abonnement dans cette période existe déjà pour cette revue.';
    END IF;

    -- On vérifie qu’aucun exemplaire ne soit déjà enregistré dans cette période (donc lié à l'abonnement qu'on veut supprimer).
    IF EXISTS (
        SELECT 1
        FROM exemplaire
        WHERE id = NEW.idRevue
        AND dateAchat BETWEEN dateCommande AND NEW.dateFinAbonnement
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Impossible de créer un abonnement : des exemplaires existent déjà pour cette période.';
    END IF;

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `commande`
--

DROP TABLE IF EXISTS `commande`;
CREATE TABLE IF NOT EXISTS `commande` (
  `id` varchar(5) NOT NULL,
  `dateCommande` date DEFAULT NULL,
  `montant` double DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `commande`
--

INSERT INTO `commande` (`id`, `dateCommande`, `montant`) VALUES
('00010', '2025-03-27', 50),
('00011', '2025-03-28', 60),
('00014', '2025-03-27', 30),
('00015', '2025-03-27', 49.99),
('00016', '2025-04-06', 12),
('00017', '2025-04-06', 11),
('00018', '2025-04-06', 10),
('00020', '2025-04-07', 11),
('00021', '2025-04-08', 45),
('00022', '2025-04-08', 45),
('00023', '2025-04-08', 45),
('00024', '2025-04-08', 45),
('00025', '2025-04-08', 45),
('00026', '2025-04-01', 45.5),
('00027', '2025-04-10', 30),
('00028', '2025-04-15', 20);

--
-- Déclencheurs `commande`
--
DROP TRIGGER IF EXISTS `commande_before_delete`;
DELIMITER $$
CREATE TRIGGER `commande_before_delete` BEFORE DELETE ON `commande` FOR EACH ROW BEGIN
  -- Si la commande n'a pas le bon statut pour être supprimée, on interdit la suppression.
  IF EXISTS (
    SELECT 1 FROM commandedocument
    WHERE id = OLD.id
      AND idSuivi IN ('00002','00003')
  ) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Suppression interdite : commande déjà livrée ou réglée.';
  ELSE
    DELETE FROM commandedocument WHERE id = OLD.id;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `commandedocument`
--

DROP TABLE IF EXISTS `commandedocument`;
CREATE TABLE IF NOT EXISTS `commandedocument` (
  `id` varchar(5) NOT NULL,
  `nbExemplaire` int DEFAULT NULL,
  `idLivreDvd` varchar(10) NOT NULL,
  `idSuivi` char(5) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idLivreDvd` (`idLivreDvd`),
  KEY `idSuivi` (`idSuivi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `commandedocument`
--

INSERT INTO `commandedocument` (`id`, `nbExemplaire`, `idLivreDvd`, `idSuivi`) VALUES
('00010', 2, '00001', '00003'),
('00011', 3, '00002', '00003'),
('00014', 1, '00001', '00003'),
('00015', 2, '00001', '00003'),
('00016', 2, '00003', '00003'),
('00017', 2, '00004', '00003'),
('00018', 1, '20002', '00003'),
('00020', 1, '20001', '00002'),
('00026', 1, '10007', '00002'),
('00027', 1, '10008', '00001'),
('00028', 3, '00015', '00002');

--
-- Déclencheurs `commandedocument`
--
DROP TRIGGER IF EXISTS `commandedocument_ajouter_exemplaires`;
DELIMITER $$
CREATE TRIGGER `commandedocument_ajouter_exemplaires` AFTER UPDATE ON `commandedocument` FOR EACH ROW BEGIN
  DECLARE reste INT; -- c'est le nombre d'exemplaires qu'il reste à insérer.
  DECLARE numeroSuivant INT; -- c'est pour donner un numéro unique à chaque exemplaire du document.
  DECLARE dateCommandeVar DATE;
  
  -- Récupérer la date de commande.
  SELECT dateCommande 
    INTO dateCommandeVar 
    FROM commande 
    WHERE id = NEW.id;
  
  -- Vérifier si le statut passe à "livrée" (idSuivi = '00002'), car s'il est toujours "en cours" il ne faut pas en générer.
  IF OLD.idSuivi <> '00002' AND NEW.idSuivi = '00002' THEN
    SET reste = NEW.nbExemplaire;
    
    WHILE reste > 0 DO
      -- Boucle jusqu’à ce qu’on ait inséré tous les exemplaires.
      SELECT COALESCE(MAX(numero), 0) + 1
        INTO numeroSuivant
        FROM exemplaire
        WHERE id = NEW.idLivreDvd;

      INSERT INTO exemplaire (id, numero, dateAchat, photo, idEtat)
      VALUES (
        NEW.idLivreDvd,
        numeroSuivant,
        dateCommandeVar,
        '',               -- photo vide par défaut
        '00001'           -- état "neuf"
      );
      
      SET reste = reste - 1;
    END WHILE;
  END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `commandedocument_check_statut`;
DELIMITER $$
CREATE TRIGGER `commandedocument_check_statut` BEFORE UPDATE ON `commandedocument` FOR EACH ROW BEGIN
  -- Si la commande est en cours, elle ne peut pas être réglée directement.
  IF OLD.idSuivi = '00001' AND NEW.idSuivi = '00003' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Une commande doit être livrée avant d être réglée.';
  END IF;

  -- Si la commande est livrée, elle peut seulement rester livrée ou passer à réglée.
  IF OLD.idSuivi = '00002' AND NEW.idSuivi NOT IN ('00002','00003') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Impossible de revenir à un statut antérieur après livraison.';
  END IF;

  -- Si la commande est réglée, elle ne peut pas changer de statut (ce qui est normal).
  IF OLD.idSuivi = '00003' AND NEW.idSuivi <> '00003' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Une commande réglée ne peut pas changer de statut.';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `document`
--

DROP TABLE IF EXISTS `document`;
CREATE TABLE IF NOT EXISTS `document` (
  `id` varchar(10) NOT NULL,
  `titre` varchar(60) DEFAULT NULL,
  `image` varchar(500) DEFAULT NULL,
  `idRayon` varchar(5) NOT NULL,
  `idPublic` varchar(5) NOT NULL,
  `idGenre` varchar(5) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idRayon` (`idRayon`),
  KEY `idPublic` (`idPublic`),
  KEY `idGenre` (`idGenre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `document`
--

INSERT INTO `document` (`id`, `titre`, `image`, `idRayon`, `idPublic`, `idGenre`) VALUES
('00001', 'Quand sort la recluse', '', 'LV003', '00002', '10014'),
('00002', 'Un pays à l\'aube', '', 'LV001', '00002', '10004'),
('00003', 'Et je danse aussi', '', 'LV002', '00003', '10013'),
('00004', 'L\'armée furieuse', '', 'LV003', '00002', '10014'),
('00005', 'Les anonymes', '', 'LV001', '00002', '10014'),
('00006', 'La marque jaune', '', 'BD001', '00003', '10001'),
('00007', 'Dans les coulisses du musée', '', 'LV001', '00003', '10006'),
('00008', 'Histoire du juif errant', '', 'LV002', '00002', '10006'),
('00009', 'Pars vite et reviens tard', '', 'LV003', '00002', '10014'),
('00010', 'Le vestibule des causes perdues', '', 'LV001', '00002', '10006'),
('00011', 'L\'île des oubliés', '', 'LV002', '00003', '10006'),
('00012', 'La souris bleue', '', 'LV002', '00003', '10006'),
('00013', 'Sacré Pêre Noël', '', 'JN001', '00001', '10001'),
('00014', 'Mauvaise étoile', '', 'LV003', '00003', '10014'),
('00015', 'La confrérie des téméraires', '', 'JN002', '00004', '10014'),
('00016', 'Le butin du requin', '', 'JN002', '00004', '10014'),
('00017', 'Catastrophes au Brésil', '', 'JN002', '00004', '10014'),
('00018', 'Le Routard - Maroc', '', 'DV005', '00003', '10011'),
('00019', 'Guide Vert - Iles Canaries', '', 'DV005', '00003', '10011'),
('00020', 'Guide Vert - Irlande', '', 'DV005', '00003', '10011'),
('00021', 'Les déferlantes', '', 'LV002', '00002', '10006'),
('00022', 'Une part de Ciel', '', 'LV002', '00002', '10006'),
('00023', 'Le secret du janissaire', '', 'BD001', '00002', '10001'),
('00024', 'Pavillon noir', '', 'BD001', '00002', '10001'),
('00025', 'L\'archipel du danger', '', 'BD001', '00002', '10001'),
('00026', 'La planète des singes', '', 'LV002', '00003', '10002'),
('10001', 'Arts Magazine', '', 'PR002', '00002', '10016'),
('10002', 'Alternatives Economiques', '', 'PR002', '00002', '10015'),
('10003', 'Challenges', '', 'PR002', '00002', '10015'),
('10004', 'Rock and Folk', '', 'PR002', '00002', '10016'),
('10005', 'Les Echos', '', 'PR001', '00002', '10015'),
('10006', 'Le Monde', '', 'PR001', '00002', '10018'),
('10007', 'Telerama', '', 'PR002', '00002', '10016'),
('10008', 'L\'Obs', '', 'PR002', '00002', '10018'),
('10009', 'L\'Equipe', '', 'PR001', '00002', '10017'),
('10010', 'L\'Equipe Magazine', '', 'PR002', '00002', '10017'),
('10011', 'Geo', '', 'PR002', '00003', '10016'),
('20001', 'Star Wars 5 L\'empire contre attaque', '', 'DF001', '00003', '10002'),
('20002', 'Le seigneur des anneaux : la communauté de l\'anneau', '', 'DF001', '00003', '10019'),
('20003', 'Jurassic Park', '', 'DF001', '00003', '10002'),
('20004', 'Matrix', '', 'DF001', '00003', '10002');

-- --------------------------------------------------------

--
-- Structure de la table `dvd`
--

DROP TABLE IF EXISTS `dvd`;
CREATE TABLE IF NOT EXISTS `dvd` (
  `id` varchar(10) NOT NULL,
  `synopsis` text,
  `realisateur` varchar(20) DEFAULT NULL,
  `duree` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `dvd`
--

INSERT INTO `dvd` (`id`, `synopsis`, `realisateur`, `duree`) VALUES
('20001', 'Luc est entraîné par Yoda pendant que Han et Leia tentent de se cacher dans la cité des nuages.', 'George Lucas', 124),
('20002', 'L\'anneau unique, forgé par Sauron, est porté par Fraudon qui l\'amène à Foncombe. De là, des représentants de peuples différents vont s\'unir pour aider Fraudon à amener l\'anneau à la montagne du Destin.', 'Peter Jackson', 228),
('20003', 'Un milliardaire et des généticiens créent des dinosaures à partir de clonage.', 'Steven Spielberg', 128),
('20004', 'Un informaticien réalise que le monde dans lequel il vit est une simulation gérée par des machines.', 'Les Wachowski', 136);

-- --------------------------------------------------------

--
-- Structure de la table `etat`
--

DROP TABLE IF EXISTS `etat`;
CREATE TABLE IF NOT EXISTS `etat` (
  `id` char(5) NOT NULL,
  `libelle` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `etat`
--

INSERT INTO `etat` (`id`, `libelle`) VALUES
('00001', 'neuf'),
('00002', 'usagé'),
('00003', 'détérioré'),
('00004', 'inutilisable');

-- --------------------------------------------------------

--
-- Structure de la table `exemplaire`
--

DROP TABLE IF EXISTS `exemplaire`;
CREATE TABLE IF NOT EXISTS `exemplaire` (
  `id` varchar(10) NOT NULL,
  `numero` int NOT NULL,
  `dateAchat` date DEFAULT NULL,
  `photo` varchar(500) NOT NULL,
  `idEtat` char(5) NOT NULL,
  PRIMARY KEY (`id`,`numero`),
  KEY `idEtat` (`idEtat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `exemplaire`
--

INSERT INTO `exemplaire` (`id`, `numero`, `dateAchat`, `photo`, `idEtat`) VALUES
('00001', 1, '2025-03-27', '', '00001'),
('00001', 2, '2025-03-27', '', '00001'),
('00001', 3, '2025-03-27', '', '00001'),
('00003', 1, '2025-04-06', '', '00001'),
('00003', 2, '2025-04-06', '', '00001'),
('00004', 1, '2025-04-06', '', '00001'),
('00004', 2, '2025-04-06', '', '00001'),
('00015', 1, '2025-04-15', '', '00001'),
('00015', 2, '2025-04-15', '', '00001'),
('00015', 3, '2025-04-15', '', '00001'),
('10002', 418, '2021-12-01', '', '00001'),
('10007', 3237, '2021-11-23', '', '00001'),
('10007', 3238, '2021-11-30', '', '00001'),
('10007', 3239, '2021-12-07', '', '00001'),
('10007', 3240, '2021-12-21', '', '00001'),
('10007', 3241, '2025-04-01', '', '00001'),
('10011', 505, '2022-10-16', '', '00001'),
('10011', 506, '2021-04-01', '', '00001'),
('10011', 507, '2021-05-03', '', '00001'),
('10011', 508, '2021-06-05', '', '00001'),
('10011', 509, '2021-07-01', '', '00001'),
('10011', 510, '2021-08-04', '', '00001'),
('10011', 511, '2021-09-01', '', '00001'),
('10011', 512, '2021-10-06', '', '00001'),
('10011', 513, '2021-11-01', '', '00001'),
('10011', 514, '2021-12-01', '', '00001'),
('20001', 1, '2025-04-07', '', '00001'),
('20002', 1, '2025-04-06', '', '00001');

-- --------------------------------------------------------

--
-- Structure de la table `genre`
--

DROP TABLE IF EXISTS `genre`;
CREATE TABLE IF NOT EXISTS `genre` (
  `id` varchar(5) NOT NULL,
  `libelle` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `genre`
--

INSERT INTO `genre` (`id`, `libelle`) VALUES
('10000', 'Humour'),
('10001', 'Bande dessinée'),
('10002', 'Science Fiction'),
('10003', 'Biographie'),
('10004', 'Historique'),
('10006', 'Roman'),
('10007', 'Aventures'),
('10008', 'Essai'),
('10009', 'Documentaire'),
('10010', 'Technique'),
('10011', 'Voyages'),
('10012', 'Drame'),
('10013', 'Comédie'),
('10014', 'Policier'),
('10015', 'Presse Economique'),
('10016', 'Presse Culturelle'),
('10017', 'Presse sportive'),
('10018', 'Actualités'),
('10019', 'Fantazy');

-- --------------------------------------------------------

--
-- Structure de la table `livre`
--

DROP TABLE IF EXISTS `livre`;
CREATE TABLE IF NOT EXISTS `livre` (
  `id` varchar(10) NOT NULL,
  `ISBN` varchar(13) DEFAULT NULL,
  `auteur` varchar(20) DEFAULT NULL,
  `collection` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `livre`
--

INSERT INTO `livre` (`id`, `ISBN`, `auteur`, `collection`) VALUES
('00001', '1234569877896', 'Fred Vargas', 'Commissaire Adamsberg'),
('00002', '1236547896541', 'Dennis Lehanne', ''),
('00003', '6541236987410', 'Anne-Laure Bondoux', ''),
('00004', '3214569874123', 'Fred Vargas', 'Commissaire Adamsberg'),
('00005', '3214563214563', 'RJ Ellory', ''),
('00006', '3213213211232', 'Edgar P. Jacobs', 'Blake et Mortimer'),
('00007', '6541236987541', 'Kate Atkinson', ''),
('00008', '1236987456321', 'Jean d\'Ormesson', ''),
('00009', '', 'Fred Vargas', 'Commissaire Adamsberg'),
('00010', '', 'Manon Moreau', ''),
('00011', '', 'Victoria Hislop', ''),
('00012', '', 'Kate Atkinson', ''),
('00013', '', 'Raymond Briggs', ''),
('00014', '', 'RJ Ellory', ''),
('00015', '', 'Floriane Turmeau', ''),
('00016', '', 'Julian Press', ''),
('00017', '', 'Philippe Masson', ''),
('00018', '', '', 'Guide du Routard'),
('00019', '', '', 'Guide Vert'),
('00020', '', '', 'Guide Vert'),
('00021', '', 'Claudie Gallay', ''),
('00022', '', 'Claudie Gallay', ''),
('00023', '', 'Ayrolles - Masbou', 'De cape et de crocs'),
('00024', '', 'Ayrolles - Masbou', 'De cape et de crocs'),
('00025', '', 'Ayrolles - Masbou', 'De cape et de crocs'),
('00026', '', 'Pierre Boulle', 'Julliard');

-- --------------------------------------------------------

--
-- Structure de la table `livres_dvd`
--

DROP TABLE IF EXISTS `livres_dvd`;
CREATE TABLE IF NOT EXISTS `livres_dvd` (
  `id` varchar(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `livres_dvd`
--

INSERT INTO `livres_dvd` (`id`) VALUES
('00001'),
('00002'),
('00003'),
('00004'),
('00005'),
('00006'),
('00007'),
('00008'),
('00009'),
('00010'),
('00011'),
('00012'),
('00013'),
('00014'),
('00015'),
('00016'),
('00017'),
('00018'),
('00019'),
('00020'),
('00021'),
('00022'),
('00023'),
('00024'),
('00025'),
('00026'),
('10002'),
('10003'),
('10004'),
('10005'),
('10006'),
('10007'),
('10008'),
('10009'),
('20001'),
('20002'),
('20003'),
('20004');

-- --------------------------------------------------------

--
-- Structure de la table `public`
--

DROP TABLE IF EXISTS `public`;
CREATE TABLE IF NOT EXISTS `public` (
  `id` varchar(5) NOT NULL,
  `libelle` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `public`
--

INSERT INTO `public` (`id`, `libelle`) VALUES
('00001', 'Jeunesse'),
('00002', 'Adultes'),
('00003', 'Tous publics'),
('00004', 'Ados');

-- --------------------------------------------------------

--
-- Structure de la table `rayon`
--

DROP TABLE IF EXISTS `rayon`;
CREATE TABLE IF NOT EXISTS `rayon` (
  `id` char(5) NOT NULL,
  `libelle` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `rayon`
--

INSERT INTO `rayon` (`id`, `libelle`) VALUES
('BD001', 'BD Adultes'),
('BL001', 'Beaux Livres'),
('DF001', 'DVD films'),
('DV001', 'Sciences'),
('DV002', 'Maison'),
('DV003', 'Santé'),
('DV004', 'Littérature classique'),
('DV005', 'Voyages'),
('JN001', 'Jeunesse BD'),
('JN002', 'Jeunesse romans'),
('LV001', 'Littérature étrangère'),
('LV002', 'Littérature française'),
('LV003', 'Policiers français étrangers'),
('PR001', 'Presse quotidienne'),
('PR002', 'Magazines');

-- --------------------------------------------------------

--
-- Structure de la table `revue`
--

DROP TABLE IF EXISTS `revue`;
CREATE TABLE IF NOT EXISTS `revue` (
  `id` varchar(10) NOT NULL,
  `periodicite` varchar(2) DEFAULT NULL,
  `delaiMiseADispo` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `revue`
--

INSERT INTO `revue` (`id`, `periodicite`, `delaiMiseADispo`) VALUES
('10001', 'MS', 52),
('10002', 'MS', 52),
('10003', 'HB', 15),
('10004', 'HB', 15),
('10005', 'QT', 5),
('10006', 'QT', 5),
('10007', 'HB', 26),
('10008', 'HB', 26),
('10009', 'QT', 5),
('10010', 'HB', 12),
('10011', 'MS', 52);

-- --------------------------------------------------------

--
-- Structure de la table `service`
--

DROP TABLE IF EXISTS `service`;
CREATE TABLE IF NOT EXISTS `service` (
  `id` int NOT NULL AUTO_INCREMENT,
  `libelle` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `service`
--

INSERT INTO `service` (`id`, `libelle`) VALUES
(1, 'Administratif'),
(2, 'Prêts'),
(3, 'Culture'),
(4, 'Administrateur');

-- --------------------------------------------------------

--
-- Structure de la table `suivi`
--

DROP TABLE IF EXISTS `suivi`;
CREATE TABLE IF NOT EXISTS `suivi` (
  `id` char(5) NOT NULL,
  `libelle` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `suivi`
--

INSERT INTO `suivi` (`id`, `libelle`) VALUES
('00001', 'en cours'),
('00002', 'livrée'),
('00003', 'réglée'),
('00004', 'relancée');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

DROP TABLE IF EXISTS `utilisateur`;
CREATE TABLE IF NOT EXISTS `utilisateur` (
  `id` int NOT NULL AUTO_INCREMENT,
  `login` varchar(100) NOT NULL,
  `motdepasse` varchar(255) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `idService` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`),
  KEY `idService` (`idService`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `utilisateur`
--

INSERT INTO `utilisateur` (`id`, `login`, `motdepasse`, `nom`, `prenom`, `idService`) VALUES
(1, 'admin', '$2y$10$zE3yvEPKB1e01U4ZHubveucau8gc3K2Zi1jdB2YIcO4rL9XBTPgYG', 'Doreto', 'Michel', 4),
(2, 'culture', '$2y$10$7/p0dhBH7dllsPSBjUbCo.Jz43nT2u2wTZUTnf60JHp1oYvbiMXAC', 'Loupia', 'Marie', 3),
(3, 'administratif', '$2y$10$NFvBz/8wgvCesvXY5UZtKecLz7f1jJIgLtsYe7fIVjtWYQpF0aAXK', 'Durand', 'Mélanie', 1),
(4, 'pret', '$2y$10$v9bMToAaXUM.qaG.roljXuMMWBtDWbD9d6rsmxqszMGgGG/ReUXPW', 'Martin', 'Mike', 2);

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `abonnement`
--
ALTER TABLE `abonnement`
  ADD CONSTRAINT `abonnement_ibfk_1` FOREIGN KEY (`id`) REFERENCES `commande` (`id`),
  ADD CONSTRAINT `abonnement_ibfk_2` FOREIGN KEY (`idRevue`) REFERENCES `revue` (`id`);

--
-- Contraintes pour la table `commandedocument`
--
ALTER TABLE `commandedocument`
  ADD CONSTRAINT `commandedocument_ibfk_1` FOREIGN KEY (`id`) REFERENCES `commande` (`id`),
  ADD CONSTRAINT `commandedocument_ibfk_2` FOREIGN KEY (`idLivreDvd`) REFERENCES `livres_dvd` (`id`),
  ADD CONSTRAINT `commandedocument_ibfk_3` FOREIGN KEY (`idSuivi`) REFERENCES `suivi` (`id`);

--
-- Contraintes pour la table `document`
--
ALTER TABLE `document`
  ADD CONSTRAINT `document_ibfk_1` FOREIGN KEY (`idRayon`) REFERENCES `rayon` (`id`),
  ADD CONSTRAINT `document_ibfk_2` FOREIGN KEY (`idPublic`) REFERENCES `public` (`id`),
  ADD CONSTRAINT `document_ibfk_3` FOREIGN KEY (`idGenre`) REFERENCES `genre` (`id`);

--
-- Contraintes pour la table `dvd`
--
ALTER TABLE `dvd`
  ADD CONSTRAINT `dvd_ibfk_1` FOREIGN KEY (`id`) REFERENCES `livres_dvd` (`id`);

--
-- Contraintes pour la table `exemplaire`
--
ALTER TABLE `exemplaire`
  ADD CONSTRAINT `exemplaire_ibfk_1` FOREIGN KEY (`id`) REFERENCES `document` (`id`),
  ADD CONSTRAINT `exemplaire_ibfk_2` FOREIGN KEY (`idEtat`) REFERENCES `etat` (`id`);

--
-- Contraintes pour la table `livre`
--
ALTER TABLE `livre`
  ADD CONSTRAINT `livre_ibfk_1` FOREIGN KEY (`id`) REFERENCES `livres_dvd` (`id`);

--
-- Contraintes pour la table `livres_dvd`
--
ALTER TABLE `livres_dvd`
  ADD CONSTRAINT `livres_dvd_ibfk_1` FOREIGN KEY (`id`) REFERENCES `document` (`id`);

--
-- Contraintes pour la table `revue`
--
ALTER TABLE `revue`
  ADD CONSTRAINT `revue_ibfk_1` FOREIGN KEY (`id`) REFERENCES `document` (`id`);

--
-- Contraintes pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD CONSTRAINT `utilisateur_ibfk_1` FOREIGN KEY (`idService`) REFERENCES `service` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
