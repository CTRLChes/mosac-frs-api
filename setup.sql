-- MoSAC-FRS MySQL Database Setup
-- Run this SQL in your Aiven (testing) or z.com (production) MySQL database

CREATE TABLE IF NOT EXISTS users (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    full_name        VARCHAR(255) NOT NULL,
    username         VARCHAR(100) NOT NULL UNIQUE,
    pin_hash         VARCHAR(255) NOT NULL,
    security_question TEXT NOT NULL,
    security_answer  VARCHAR(255) NOT NULL,
    role             VARCHAR(50) NOT NULL DEFAULT 'Farmer',
    profile_pic      TEXT
);

CREATE TABLE IF NOT EXISTS cropInfo (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    crop_name        VARCHAR(255) NOT NULL,
    crop_type        VARCHAR(100) NOT NULL,
    crop_description TEXT NOT NULL,
    nitrogen_min     FLOAT,
    nitrogen_max     FLOAT,
    phosphorus_min   FLOAT,
    phosphorus_max   FLOAT,
    potassium_min    FLOAT,
    potassium_max    FLOAT,
    soil_ph_min      FLOAT,
    soil_ph_max      FLOAT,
    moisture_min     FLOAT,
    moisture_max     FLOAT,
    pic1_url         TEXT NOT NULL,
    pic2_url         TEXT,
    pic3_url         TEXT,
    pic4_url         TEXT,
    pic5_url         TEXT
);

CREATE TABLE IF NOT EXISTS fertilizer (
    fert_id            INT AUTO_INCREMENT PRIMARY KEY,
    fertilizer_name    VARCHAR(255) NOT NULL,
    fertilizer_type    VARCHAR(100) NOT NULL,
    crop_type          VARCHAR(100) NOT NULL,
    nitrogen_content   FLOAT NOT NULL,
    phosphorus_content FLOAT NOT NULL,
    potassium_content  FLOAT NOT NULL,
    application_method VARCHAR(255) NOT NULL,
    application_time   VARCHAR(255) NOT NULL,
    description        TEXT NOT NULL,
    fert_pic1_url      TEXT NOT NULL,
    fert_pic2_url      TEXT,
    fert_pic3_url      TEXT
);

CREATE TABLE IF NOT EXISTS evaluation (
    eval_id    INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(100) NOT NULL,
    nitrogen   FLOAT NOT NULL,
    phosphorus FLOAT NOT NULL,
    potassium  FLOAT NOT NULL,
    soil_ph    FLOAT NOT NULL,
    moisture   FLOAT NOT NULL,
    latitude   FLOAT NOT NULL,
    longitude  FLOAT NOT NULL,
    location   TEXT,
    date       VARCHAR(20) NOT NULL,
    time       VARCHAR(20) NOT NULL
);
