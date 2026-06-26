-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 26, 2026 at 01:57 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gestion_compteurs`
--

-- --------------------------------------------------------

--
-- Table structure for table `client`
--

CREATE TABLE `client` (
  `idClient` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `adresse` varchar(255) NOT NULL,
  `codePostal` varchar(20) DEFAULT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `pays` varchar(50) DEFAULT 'Congo',
  `email` varchar(100) NOT NULL,
  `motDePasse` varchar(255) NOT NULL,
  `Telephone` varchar(20) NOT NULL,
  `Telephone2` varchar(20) DEFAULT NULL,
  `dateInscription` datetime DEFAULT current_timestamp(),
  `dateNaissance` date DEFAULT NULL,
  `sexe` enum('M','F','Autre') DEFAULT NULL,
  `numeroIdentite` varchar(50) DEFAULT NULL,
  `typeIdentite` varchar(50) DEFAULT NULL,
  `statut` enum('actif','inactif','suspendu') DEFAULT 'actif',
  `dateCreation` timestamp NOT NULL DEFAULT current_timestamp(),
  `dateModification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client`
--

INSERT INTO `client` (`idClient`, `nom`, `prenom`, `adresse`, `codePostal`, `ville`, `pays`, `email`, `motDePasse`, `Telephone`, `Telephone2`, `dateInscription`, `dateNaissance`, `sexe`, `numeroIdentite`, `typeIdentite`, `statut`, `dateCreation`, `dateModification`) VALUES
(1, 'Jacob', 'Mishiki', 'Goma', 'BP123', 'Goma', 'Congo', 'jacobmishiki@gmail.com', '$2y$10$VvtPIvrT4MVTfX6E9W64gelSaghtxK.zf9aWyvXCK/MddOiulYCLC', '0990505916', '', '2026-06-25 19:28:55', '2002-06-25', 'M', NULL, NULL, 'actif', '2026-06-25 17:28:55', '2026-06-25 17:28:55'),
(2, 'MARIE ', 'ATOSHA', 'KATOYI', 'BP123', 'Goma', 'Congo', 'mariekasonja@gmail.com', '$2y$10$v41eYbH6fkO7TLGm1NgG6OhtvlBVJuLRdxU7VumC1IdF4iSuMjoHi', '0862269833', '', '2026-06-26 13:28:44', '2005-12-17', 'F', NULL, NULL, 'actif', '2026-06-26 11:28:44', '2026-06-26 11:28:44');

-- --------------------------------------------------------

--
-- Table structure for table `compteur`
--

CREATE TABLE `compteur` (
  `idCompteur` int(11) NOT NULL,
  `NumeroSerie` varchar(50) NOT NULL,
  `DateInstallation` date NOT NULL,
  `DateDerniereVerification` date DEFAULT NULL,
  `ProchaineVerification` date DEFAULT NULL,
  `etat` enum('actif','inactif','hors_service','en_panne','suspendu') DEFAULT 'actif',
  `indexActuel` varchar(50) NOT NULL,
  `typeCompteur` enum('monophase','triphase','prepaye') DEFAULT 'monophase',
  `marque` varchar(100) DEFAULT NULL,
  `modele` varchar(100) DEFAULT NULL,
  `capacite` int(11) DEFAULT NULL COMMENT 'Capacité en ampères',
  `tension` varchar(20) DEFAULT NULL,
  `emplacement` varchar(255) DEFAULT NULL,
  `coordonneesGPS` varchar(100) DEFAULT NULL,
  `idClient` int(11) DEFAULT NULL,
  `dateCreation` timestamp NOT NULL DEFAULT current_timestamp(),
  `dateModification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `compteur`
--

INSERT INTO `compteur` (`idCompteur`, `NumeroSerie`, `DateInstallation`, `DateDerniereVerification`, `ProchaineVerification`, `etat`, `indexActuel`, `typeCompteur`, `marque`, `modele`, `capacite`, `tension`, `emplacement`, `coordonneesGPS`, `idClient`, `dateCreation`, `dateModification`) VALUES
(1, 'SNEL-2026-001', '2026-06-26', NULL, NULL, 'actif', '50', 'prepaye', 'Mon compteur', 'Compte123', 60, '220', 'Kyeshero', '123456', 1, '2026-06-26 05:25:41', '2026-06-26 06:30:07'),
(2, 'SNEL-2026-002', '2026-06-26', NULL, NULL, 'actif', '40', 'triphase', 'CASH POWER', 'GUSHI', 60, '220', 'KATOYI', '77887887983', 2, '2026-06-26 11:36:44', '2026-06-26 11:40:11');

-- --------------------------------------------------------

--
-- Table structure for table `consommation`
--

CREATE TABLE `consommation` (
  `idConsommation` int(11) NOT NULL,
  `dateDebut` date NOT NULL,
  `dateFin` date NOT NULL,
  `indexAncien` varchar(50) NOT NULL,
  `indexNouveau` varchar(50) NOT NULL,
  `quantiteCons` float NOT NULL,
  `consommationJournaliere` float DEFAULT NULL,
  `consommationMoyenne` float DEFAULT NULL,
  `periode` varchar(20) DEFAULT NULL COMMENT 'mensuel, trimestriel, annuel',
  `saison` enum('ete','hiver','printemps','automne') DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `idCompteur` int(11) DEFAULT NULL,
  `idAgentReleve` int(11) DEFAULT NULL,
  `dateReleve` datetime DEFAULT current_timestamp(),
  `dateCreation` timestamp NOT NULL DEFAULT current_timestamp(),
  `dateModification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consommation`
--

INSERT INTO `consommation` (`idConsommation`, `dateDebut`, `dateFin`, `indexAncien`, `indexNouveau`, `quantiteCons`, `consommationJournaliere`, `consommationMoyenne`, `periode`, `saison`, `observations`, `idCompteur`, `idAgentReleve`, `dateReleve`, `dateCreation`, `dateModification`) VALUES
(1, '2026-05-26', '2026-06-26', '30', '50', 20, 0.645161, NULL, NULL, NULL, 'Bon etat', 1, 1, '2026-06-26 08:30:07', '2026-06-26 06:30:07', '2026-06-26 06:30:07'),
(2, '2026-05-26', '2026-06-26', '20', '40', 20, 0.645161, NULL, NULL, NULL, 'Bien', 2, 1, '2026-06-26 13:40:11', '2026-06-26 11:40:11', '2026-06-26 11:40:11');

-- --------------------------------------------------------

--
-- Table structure for table `facture`
--

CREATE TABLE `facture` (
  `idFacture` int(11) NOT NULL,
  `numeroFacture` varchar(50) NOT NULL,
  `dateEmission` date NOT NULL,
  `dateEcheance` date NOT NULL,
  `dateLimitePaiement` date NOT NULL,
  `datePaiementReel` date DEFAULT NULL,
  `montantTotal` float NOT NULL,
  `montantHT` float DEFAULT NULL,
  `montantTVA` float DEFAULT NULL,
  `tauxTVA` float DEFAULT 18,
  `montantPenalite` float DEFAULT 0,
  `montantReduction` float DEFAULT 0,
  `remise` float DEFAULT 0,
  `statut` enum('en_attente','payee','en_retard','annulee','impayee') DEFAULT 'en_attente',
  `typeFacture` enum('normale','regulatrice','penalite') DEFAULT 'normale',
  `periodeConsoDebut` date DEFAULT NULL,
  `periodeConsoFin` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `idClient` int(11) DEFAULT NULL,
  `idConsommation` int(11) DEFAULT NULL,
  `idAgentCreation` int(11) DEFAULT NULL,
  `dateCreation` timestamp NOT NULL DEFAULT current_timestamp(),
  `dateModification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facture`
--

INSERT INTO `facture` (`idFacture`, `numeroFacture`, `dateEmission`, `dateEcheance`, `dateLimitePaiement`, `datePaiementReel`, `montantTotal`, `montantHT`, `montantTVA`, `tauxTVA`, `montantPenalite`, `montantReduction`, `remise`, `statut`, `typeFacture`, `periodeConsoDebut`, `periodeConsoFin`, `description`, `idClient`, `idConsommation`, `idAgentCreation`, `dateCreation`, `dateModification`) VALUES
(1, 'FAC-202606-000001', '2026-06-26', '2026-07-11', '2026-07-26', NULL, 1500, NULL, NULL, 18, 0, 0, 0, 'en_attente', 'normale', '2026-05-26', '2026-06-26', NULL, 1, 1, 1, '2026-06-26 06:30:07', '2026-06-26 06:30:07'),
(2, 'FAC-202606-000002', '2026-06-26', '2026-07-11', '2026-07-26', NULL, 1500, NULL, NULL, 18, 0, 0, 0, 'en_attente', 'normale', '2026-05-26', '2026-06-26', NULL, 2, 2, 1, '2026-06-26 11:40:11', '2026-06-26 11:40:11');

-- --------------------------------------------------------

--
-- Table structure for table `historiqueactions`
--

CREATE TABLE `historiqueactions` (
  `idHistorique` int(11) NOT NULL,
  `tableConcernee` varchar(100) DEFAULT NULL,
  `idEnregistrement` int(11) DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL COMMENT 'INSERT, UPDATE, DELETE',
  `anciennesValeurs` text DEFAULT NULL,
  `nouvellesValeurs` text DEFAULT NULL,
  `idUtilisateur` int(11) DEFAULT NULL,
  `adresseIP` varchar(45) DEFAULT NULL,
  `userAgent` text DEFAULT NULL,
  `dateAction` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `idNotification` int(11) NOT NULL,
  `idClient` int(11) DEFAULT NULL,
  `idUtilisateur` int(11) DEFAULT NULL,
  `titre` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','alerte','rappel','promotion','urgent') DEFAULT 'info',
  `priorite` int(11) DEFAULT 1,
  `estLue` tinyint(1) DEFAULT 0,
  `dateLecture` datetime DEFAULT NULL,
  `dateExpiration` date DEFAULT NULL,
  `lien` varchar(255) DEFAULT NULL,
  `dateCreation` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`idNotification`, `idClient`, `idUtilisateur`, `titre`, `message`, `type`, `priorite`, `estLue`, `dateLecture`, `dateExpiration`, `lien`, `dateCreation`) VALUES
(1, 1, NULL, 'Nouveau compteur installé', 'Un compteur a été installé chez vous. Numéro de série: SNEL-2026-001', 'info', 1, 0, NULL, NULL, NULL, '2026-06-26 05:25:41'),
(2, 1, NULL, 'Nouvelle facture disponible', 'Votre facture de 1 500 FCFA est disponible. Consommation: 20,00 kWh. Date limite: 26/07/2026', 'alerte', 1, 0, NULL, NULL, '/Pages/Client/Factures/index.php', '2026-06-26 06:30:07'),
(3, 2, NULL, 'Nouveau compteur installé', 'Un compteur a été installé chez vous. Numéro de série: SNEL-2026-002', 'info', 1, 0, NULL, NULL, NULL, '2026-06-26 11:36:44'),
(4, 2, NULL, 'Nouvelle facture disponible', 'Votre facture de 1 500 FCFA est disponible. Consommation: 20,00 kWh. Date limite: 26/07/2026', 'alerte', 1, 0, NULL, NULL, '/Pages/Client/Factures/index.php', '2026-06-26 11:40:11');

-- --------------------------------------------------------

--
-- Table structure for table `paiement`
--

CREATE TABLE `paiement` (
  `idPaiement` int(11) NOT NULL,
  `numeroReference` varchar(50) NOT NULL,
  `datePaiement` date NOT NULL,
  `dateEnregistrement` datetime DEFAULT current_timestamp(),
  `montant` float NOT NULL,
  `montantPaye` float DEFAULT NULL,
  `monnaie` varchar(10) DEFAULT 'XAF',
  `modePaiement` enum('especes','carte_bancaire','virement','mobile_money','cheque','prelevement') NOT NULL,
  `statut` enum('effectue','en_attente','echoue','annule','rembourse') DEFAULT 'effectue',
  `referenceTransaction` varchar(100) DEFAULT NULL,
  `codeTransaction` varchar(100) DEFAULT NULL,
  `banque` varchar(100) DEFAULT NULL,
  `nomTitulaire` varchar(200) DEFAULT NULL,
  `penalitePayee` float DEFAULT 0,
  `idFacture` int(11) DEFAULT NULL,
  `idClient` int(11) DEFAULT NULL,
  `idAgentEnregistrement` int(11) DEFAULT NULL,
  `dateCreation` timestamp NOT NULL DEFAULT current_timestamp(),
  `dateModification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `signalements`
--

CREATE TABLE `signalements` (
  `idSignalement` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `idClient` int(11) NOT NULL,
  `idCompteur` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `consommationActuelle` decimal(10,2) DEFAULT NULL,
  `priorite` enum('basse','moyenne','haute') DEFAULT 'moyenne',
  `statut` enum('en_attente','en_cours','resolu','rejete') DEFAULT 'en_attente',
  `idAgentTraite` int(11) DEFAULT NULL,
  `commentaireAgent` text DEFAULT NULL,
  `dateCreation` datetime DEFAULT current_timestamp(),
  `dateTraite` datetime DEFAULT NULL,
  `dateModification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tarifs`
--

CREATE TABLE `tarifs` (
  `idTarif` int(11) NOT NULL,
  `categorie` varchar(100) NOT NULL,
  `trancheMin` float DEFAULT NULL,
  `trancheMax` float DEFAULT NULL,
  `prixUnitaire` float NOT NULL,
  `unite` varchar(20) DEFAULT 'kWh',
  `type` enum('residentiel','commercial','industriel') DEFAULT 'residentiel',
  `dateDebutValidite` date NOT NULL,
  `dateFinValidite` date DEFAULT NULL,
  `estActif` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `dateCreation` timestamp NOT NULL DEFAULT current_timestamp(),
  `dateModification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `idUtilisateur` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `motDePasse` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `dateEmbauche` date DEFAULT NULL,
  `dateNaissance` date DEFAULT NULL,
  `sexe` enum('M','F','Autre') DEFAULT NULL,
  `statut` enum('actif','inactif','suspendu') DEFAULT 'actif',
  `derniereConnexion` datetime DEFAULT NULL,
  `tentativeConnexion` int(11) DEFAULT 0,
  `bloque` tinyint(1) DEFAULT 0,
  `dateCreation` timestamp NOT NULL DEFAULT current_timestamp(),
  `dateModification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `utilisateurs`
--

INSERT INTO `utilisateurs` (`idUtilisateur`, `nom`, `prenom`, `email`, `motDePasse`, `role`, `telephone`, `dateEmbauche`, `dateNaissance`, `sexe`, `statut`, `derniereConnexion`, `tentativeConnexion`, `bloque`, `dateCreation`, `dateModification`) VALUES
(1, 'ALEX', 'ALEX', 'alex@gmail.com', '$2y$10$QXWOARh6gZqFxfSZP9/lze/pi3xcFYL2oZxSg0iV80sZtM1gxBdB2', 'agent', '0990674538', '2002-06-01', '2006-05-01', 'M', 'actif', '2026-06-26 13:53:21', 0, 0, '2026-06-26 04:39:41', '2026-06-26 11:53:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `client`
--
ALTER TABLE `client`
  ADD PRIMARY KEY (`idClient`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `compteur`
--
ALTER TABLE `compteur`
  ADD PRIMARY KEY (`idCompteur`),
  ADD UNIQUE KEY `NumeroSerie` (`NumeroSerie`),
  ADD KEY `idClient` (`idClient`);

--
-- Indexes for table `consommation`
--
ALTER TABLE `consommation`
  ADD PRIMARY KEY (`idConsommation`),
  ADD KEY `idCompteur` (`idCompteur`),
  ADD KEY `idAgentReleve` (`idAgentReleve`);

--
-- Indexes for table `facture`
--
ALTER TABLE `facture`
  ADD PRIMARY KEY (`idFacture`),
  ADD UNIQUE KEY `numeroFacture` (`numeroFacture`),
  ADD KEY `idClient` (`idClient`),
  ADD KEY `idConsommation` (`idConsommation`),
  ADD KEY `idAgentCreation` (`idAgentCreation`);

--
-- Indexes for table `historiqueactions`
--
ALTER TABLE `historiqueactions`
  ADD PRIMARY KEY (`idHistorique`),
  ADD KEY `idUtilisateur` (`idUtilisateur`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`idNotification`),
  ADD KEY `idClient` (`idClient`),
  ADD KEY `idUtilisateur` (`idUtilisateur`);

--
-- Indexes for table `paiement`
--
ALTER TABLE `paiement`
  ADD PRIMARY KEY (`idPaiement`),
  ADD UNIQUE KEY `numeroReference` (`numeroReference`),
  ADD KEY `idFacture` (`idFacture`),
  ADD KEY `idClient` (`idClient`),
  ADD KEY `idAgentEnregistrement` (`idAgentEnregistrement`);

--
-- Indexes for table `signalements`
--
ALTER TABLE `signalements`
  ADD PRIMARY KEY (`idSignalement`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idCompteur` (`idCompteur`),
  ADD KEY `idAgentTraite` (`idAgentTraite`),
  ADD KEY `idx_signalements_client` (`idClient`),
  ADD KEY `idx_signalements_statut` (`statut`),
  ADD KEY `idx_signalements_priorite` (`priorite`),
  ADD KEY `idx_signalements_date` (`dateCreation`);

--
-- Indexes for table `tarifs`
--
ALTER TABLE `tarifs`
  ADD PRIMARY KEY (`idTarif`);

--
-- Indexes for table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`idUtilisateur`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `client`
--
ALTER TABLE `client`
  MODIFY `idClient` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `compteur`
--
ALTER TABLE `compteur`
  MODIFY `idCompteur` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `consommation`
--
ALTER TABLE `consommation`
  MODIFY `idConsommation` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `facture`
--
ALTER TABLE `facture`
  MODIFY `idFacture` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `historiqueactions`
--
ALTER TABLE `historiqueactions`
  MODIFY `idHistorique` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `idNotification` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `paiement`
--
ALTER TABLE `paiement`
  MODIFY `idPaiement` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `signalements`
--
ALTER TABLE `signalements`
  MODIFY `idSignalement` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tarifs`
--
ALTER TABLE `tarifs`
  MODIFY `idTarif` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `idUtilisateur` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `compteur`
--
ALTER TABLE `compteur`
  ADD CONSTRAINT `compteur_ibfk_1` FOREIGN KEY (`idClient`) REFERENCES `client` (`idClient`) ON DELETE SET NULL;

--
-- Constraints for table `consommation`
--
ALTER TABLE `consommation`
  ADD CONSTRAINT `consommation_ibfk_1` FOREIGN KEY (`idCompteur`) REFERENCES `compteur` (`idCompteur`) ON DELETE CASCADE,
  ADD CONSTRAINT `consommation_ibfk_2` FOREIGN KEY (`idAgentReleve`) REFERENCES `utilisateurs` (`idUtilisateur`) ON DELETE SET NULL;

--
-- Constraints for table `facture`
--
ALTER TABLE `facture`
  ADD CONSTRAINT `facture_ibfk_1` FOREIGN KEY (`idClient`) REFERENCES `client` (`idClient`) ON DELETE CASCADE,
  ADD CONSTRAINT `facture_ibfk_2` FOREIGN KEY (`idConsommation`) REFERENCES `consommation` (`idConsommation`) ON DELETE SET NULL,
  ADD CONSTRAINT `facture_ibfk_3` FOREIGN KEY (`idAgentCreation`) REFERENCES `utilisateurs` (`idUtilisateur`) ON DELETE SET NULL;

--
-- Constraints for table `historiqueactions`
--
ALTER TABLE `historiqueactions`
  ADD CONSTRAINT `historiqueactions_ibfk_1` FOREIGN KEY (`idUtilisateur`) REFERENCES `utilisateurs` (`idUtilisateur`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`idClient`) REFERENCES `client` (`idClient`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`idUtilisateur`) REFERENCES `utilisateurs` (`idUtilisateur`) ON DELETE CASCADE;

--
-- Constraints for table `paiement`
--
ALTER TABLE `paiement`
  ADD CONSTRAINT `paiement_ibfk_1` FOREIGN KEY (`idFacture`) REFERENCES `facture` (`idFacture`) ON DELETE CASCADE,
  ADD CONSTRAINT `paiement_ibfk_2` FOREIGN KEY (`idClient`) REFERENCES `client` (`idClient`) ON DELETE CASCADE,
  ADD CONSTRAINT `paiement_ibfk_3` FOREIGN KEY (`idAgentEnregistrement`) REFERENCES `utilisateurs` (`idUtilisateur`) ON DELETE SET NULL;

--
-- Constraints for table `signalements`
--
ALTER TABLE `signalements`
  ADD CONSTRAINT `signalements_ibfk_1` FOREIGN KEY (`idClient`) REFERENCES `client` (`idClient`) ON DELETE CASCADE,
  ADD CONSTRAINT `signalements_ibfk_2` FOREIGN KEY (`idCompteur`) REFERENCES `compteur` (`idCompteur`) ON DELETE SET NULL,
  ADD CONSTRAINT `signalements_ibfk_3` FOREIGN KEY (`idAgentTraite`) REFERENCES `utilisateurs` (`idUtilisateur`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
