#include <Wire.h>
#include <LiquidCrystal_I2C.h>

LiquidCrystal_I2C lcd(0x27, 20, 4);

#define SOUND_PIN A0
#define LED_RED 4
#define LED_YELLOW 3
#define LED_GREEN 2
#define BUZZER_PIN 9

// Sound settings
int threshold = 150;
float sensitivity = 1.0;
int violationCount = 0;
int baselineNoise = 0;
bool noiseActive = false;
unsigned long lastViolationTime = 0;
const unsigned long violationCooldown = 3000;

// Averaging
const int numReadings = 10;
int readings[numReadings];
int readIndex = 0;
long total = 0;
int averageSound = 0;
int previousSound = 0;
int soundChange = 0;
const int minChangeForSound = 20;

// LCD update
unsigned long lastLCDUpdate = 0;
const unsigned long lcdUpdateInterval = 200;
unsigned long noiseMessageStartTime = 0;
bool showingNoiseMessage = false;
const unsigned long noiseMessageDuration = 4000;

// Serial command buffer
String serialBuffer = "";
String currentZone = "READING ROOM";

// Custom characters
byte soundWave[8] = {
  0b00100,
  0b01010,
  0b10001,
  0b00000,
  0b01010,
  0b00100,
  0b00000,
  0b00000
};

byte bell[8] = {
  0b00100,
  0b01110,
  0b01110,
  0b11111,
  0b11111,
  0b01110,
  0b00100,
  0b00000
};

void setup() {
  Serial.begin(9600);
  
  pinMode(LED_RED, OUTPUT);
  pinMode(LED_YELLOW, OUTPUT);
  pinMode(LED_GREEN, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);
  
  for (int i = 0; i < numReadings; i++) {
    readings[i] = 0;
  }
  
  digitalWrite(LED_RED, LOW);
  digitalWrite(LED_YELLOW, LOW);
  digitalWrite(LED_GREEN, LOW);
  digitalWrite(BUZZER_PIN, LOW);

  lcd.init();
  lcd.backlight();
  lcd.createChar(0, soundWave);
  lcd.createChar(1, bell);
  
  lcd.clear();
  lcd.setCursor(4, 0);
  lcd.print("NOISE MONITOR");
  lcd.setCursor(6, 1);
  lcd.write(0);
  lcd.print(" v3.0 ");
  lcd.write(0);
  lcd.setCursor(3, 2);
  lcd.print("INITIALIZING");
  lcd.setCursor(5, 3);
  for(int i = 0; i < 10; i++) {
    lcd.print(".");
    delay(200);
  }
  
  calibrateBaseline();
  
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("=== NOISE MONITOR ===");
  lcd.setCursor(0, 1);
  lcd.print("Threshold: ");
  lcd.print(threshold);
  lcd.setCursor(0, 2);
  lcd.print("Sensitivity: x");
  lcd.print(sensitivity);
  lcd.setCursor(0, 3);
  lcd.print("Ready!");
  delay(1500);
  
  Serial.println("READY");
  Serial.println("Noise Monitor Started!");
}

void calibrateBaseline() {
  lcd.clear();
  lcd.setCursor(2, 0);
  lcd.print("CALIBRATION");
  lcd.setCursor(0, 1);
  lcd.print("Please be quiet...");
  
  long sum = 0;
  for (int i = 0; i < 100; i++) {
    sum += analogRead(SOUND_PIN);
    delay(20);
    
    int progress = map(i, 0, 100, 0, 16);
    lcd.setCursor(0, 3);
    for (int p = 0; p < 16; p++) {
      if (p < progress) lcd.print("=");
      else lcd.print(" ");
    }
  }
  baselineNoise = (int)(sum / 100);
  
  threshold = baselineNoise + 30;
  if (threshold < 50) threshold = 50;
  if (threshold > 200) threshold = 200;
  
  lcd.clear();
  lcd.setCursor(3, 0);
  lcd.print("CALIBRATED!");
  lcd.setCursor(0, 1);
  lcd.print("Base: ");
  lcd.print(baselineNoise);
  lcd.setCursor(10, 1);
  lcd.print("Thr: ");
  lcd.print(threshold);
  delay(2000);
}

void loop() {
  // ===== CHECK SERIAL COMMANDS =====
  while (Serial.available()) {
    char c = Serial.read();
    if (c == '\n') {
      processCommand(serialBuffer);
      serialBuffer = "";
    } else {
      serialBuffer += c;
    }
  }
  
  // ===== READ SENSOR =====
  int rawLevel = analogRead(SOUND_PIN);
  int soundLevel = (int)(rawLevel * sensitivity);
  if (soundLevel > 1023) soundLevel = 1023;
  if (soundLevel < 0) soundLevel = 0;
  
  soundChange = abs(soundLevel - previousSound);
  previousSound = soundLevel;
  
  total = total - readings[readIndex];
  readings[readIndex] = soundLevel;
  total = total + readings[readIndex];
  readIndex = (readIndex + 1) % numReadings;
  averageSound = total / numReadings;
  
  bool isRealSound = (soundChange > minChangeForSound) && (averageSound > threshold);
  int percentLevel = map(constrain(averageSound, 0, 1023), 0, 1023, 0, 100);
  
  // ===== LED & BUZZER =====
  if (isRealSound) {
    digitalWrite(LED_GREEN, LOW);
    digitalWrite(LED_YELLOW, LOW);
    digitalWrite(LED_RED, HIGH);
    
    int buzzerIntensity = map(constrain(averageSound, threshold, 1023), threshold, 1023, 100, 255);
    analogWrite(BUZZER_PIN, buzzerIntensity);
    
    if (!noiseActive && (millis() - lastViolationTime > violationCooldown)) {
      noiseActive = true;
      violationCount++;
      lastViolationTime = millis();
      showingNoiseMessage = true;
      noiseMessageStartTime = millis();
      
      Serial.print("INCIDENT:");
      Serial.print(violationCount);
      Serial.print(",");
      Serial.print(averageSound);
      Serial.print(",");
      Serial.print(percentLevel);
      Serial.println();
      
      for(int i = 0; i < 3; i++) {
        digitalWrite(LED_RED, HIGH);
        digitalWrite(LED_YELLOW, HIGH);
        digitalWrite(LED_GREEN, HIGH);
        delay(100);
        digitalWrite(LED_RED, LOW);
        digitalWrite(LED_YELLOW, LOW);
        digitalWrite(LED_GREEN, LOW);
        delay(100);
      }
      digitalWrite(LED_RED, HIGH);
    }
  } 
  else if (averageSound > threshold * 0.6 && soundChange > 10) {
    digitalWrite(LED_GREEN, LOW);
    digitalWrite(LED_YELLOW, HIGH);
    digitalWrite(LED_RED, LOW);
    digitalWrite(BUZZER_PIN, LOW);
    noiseActive = false;
  }
  else {
    digitalWrite(LED_GREEN, HIGH);
    digitalWrite(LED_YELLOW, LOW);
    digitalWrite(LED_RED, LOW);
    digitalWrite(BUZZER_PIN, LOW);
    noiseActive = false;
  }
  
  // ===== SEND DATA =====
  Serial.print("DATA:");
  Serial.print(averageSound);
  Serial.print(",");
  Serial.print(percentLevel);
  Serial.print(",");
  
  if (isRealSound) Serial.print("NOISE");
  else if (averageSound > threshold * 0.6) Serial.print("WARNING");
  else Serial.print("QUIET");
  
  Serial.print(",");
  Serial.print(threshold);
  Serial.print(",");
  Serial.print(violationCount);
  Serial.print(",");
  Serial.print(baselineNoise);
  Serial.print(",");
  Serial.println(sensitivity);
  
  updateModernLCD(averageSound, percentLevel, threshold, violationCount, baselineNoise, sensitivity, isRealSound);
  
  delay(50);
}

// ============================================================
// COMMAND PROCESSING - FIXED
// ============================================================

void processCommand(String cmd) {
  cmd.trim();
  if (cmd.length() == 0) return;
  
  // Remove CMD: prefix if present
  if (cmd.startsWith("CMD:")) {
    cmd = cmd.substring(4);
  }
  cmd.trim();
  
  // ===== DEBUG: Print what we received =====
  Serial.print("CMD:{\"command\":\"DEBUG\",\"received\":\"");
  Serial.print(cmd);
  Serial.println("\"}");
  
  // ===== COMMAND: SET_THRESHOLD =====
  if (cmd.indexOf("SET_THRESHOLD") >= 0) {
    int value = extractValue(cmd);
    if (value > 0 && value <= 1023) {
      threshold = value;
      Serial.print("CMD:{\"command\":\"SET_THRESHOLD\",\"value\":");
      Serial.print(threshold);
      Serial.println(",\"status\":\"ok\"}");
      
      if (threshold > 120) {
        currentZone = "READING ROOM";
      } else {
        currentZone = "SILENT STUDY";
      }
      
      // Update LCD
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("Threshold Updated!");
      lcd.setCursor(0, 1);
      lcd.print("New Value: ");
      lcd.print(threshold);
      delay(1500);
    } else {
      Serial.println("CMD:{\"command\":\"SET_THRESHOLD\",\"status\":\"error\",\"message\":\"Invalid value\"}");
    }
    return;
  }
  
  // ===== COMMAND: SET_SENSITIVITY =====
  if (cmd.indexOf("SET_SENSITIVITY") >= 0) {
    float value = extractFloat(cmd);
    if (value > 0 && value <= 10.0) {
      sensitivity = value;
      Serial.print("CMD:{\"command\":\"SET_SENSITIVITY\",\"value\":");
      Serial.print(sensitivity);
      Serial.println(",\"status\":\"ok\"}");
      
      // Update LCD
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("Sensitivity Updated!");
      lcd.setCursor(0, 1);
      lcd.print("New Value: x");
      lcd.print(sensitivity);
      delay(1500);
    } else {
      Serial.println("CMD:{\"command\":\"SET_SENSITIVITY\",\"status\":\"error\",\"message\":\"Invalid value\"}");
    }
    return;
  }
  
  // ===== COMMAND: RESET_VIOLATIONS =====
  if (cmd.indexOf("RESET_VIOLATIONS") >= 0) {
    violationCount = 0;
    Serial.println("CMD:{\"command\":\"RESET_VIOLATIONS\",\"status\":\"ok\"}");
    
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Violations Reset!");
    delay(1000);
    return;
  }
  
  // ===== COMMAND: RECALIBRATE =====
  if (cmd.indexOf("RECALIBRATE") >= 0) {
    calibrateBaseline();
    Serial.println("CMD:{\"command\":\"RECALIBRATE\",\"status\":\"ok\"}");
    return;
  }
  
  // ===== COMMAND: STATUS =====
  if (cmd.indexOf("STATUS") >= 0) {
    Serial.print("CMD:{\"command\":\"STATUS\",\"threshold\":");
    Serial.print(threshold);
    Serial.print(",\"sensitivity\":");
    Serial.print(sensitivity);
    Serial.print(",\"violations\":");
    Serial.print(violationCount);
    Serial.print(",\"baseline\":");
    Serial.print(baselineNoise);
    Serial.println(",\"status\":\"ok\"}");
    return;
  }
  
  // ===== UNKNOWN COMMAND =====
  Serial.println("CMD:{\"command\":\"UNKNOWN\",\"status\":\"error\"}");
}

// ============================================================
// FIXED: VALUE EXTRACTORS
// ============================================================

int extractValue(String str) {
  // Find "value" in the string
  int pos = str.indexOf("value");
  if (pos < 0) return 0;
  
  // Find the colon after "value"
  pos = str.indexOf(":", pos);
  if (pos < 0) return 0;
  
  // Skip spaces and quotes
  pos++;
  while (pos < str.length() && (str.charAt(pos) == ' ' || str.charAt(pos) == '"')) {
    pos++;
  }
  
  // Extract the number
  String numStr = "";
  while (pos < str.length()) {
    char c = str.charAt(pos);
    if (c >= '0' && c <= '9') {
      numStr += c;
      pos++;
    } else {
      break;
    }
  }
  
  if (numStr.length() == 0) return 0;
  return numStr.toInt();
}

float extractFloat(String str) {
  // Find "value" in the string
  int pos = str.indexOf("value");
  if (pos < 0) return 0.0;
  
  // Find the colon after "value"
  pos = str.indexOf(":", pos);
  if (pos < 0) return 0.0;
  
  // Skip spaces and quotes
  pos++;
  while (pos < str.length() && (str.charAt(pos) == ' ' || str.charAt(pos) == '"')) {
    pos++;
  }
  
  // Extract the number (including decimal point)
  String numStr = "";
  while (pos < str.length()) {
    char c = str.charAt(pos);
    if ((c >= '0' && c <= '9') || c == '.') {
      numStr += c;
      pos++;
    } else {
      break;
    }
  }
  
  if (numStr.length() == 0) return 0.0;
  return numStr.toFloat();
}

// ============================================================
// LCD UPDATE
// ============================================================

void updateModernLCD(int sound, int percent, int thr, int violations, int baseline, float sens, bool isSound) {
  if (showingNoiseMessage) {
    if (millis() - noiseMessageStartTime >= noiseMessageDuration) {
      showingNoiseMessage = false;
    } else {
      lcd.clear();
      lcd.setCursor(2, 0);
      lcd.write(1);
      lcd.print(" WARNING! ");
      lcd.write(1);
      lcd.setCursor(1, 1);
      lcd.print("TOO LOUD!");
      lcd.setCursor(0, 2);
      lcd.print("Please be quiet!");
      lcd.setCursor(0, 3);
      lcd.print("Level: ");
      lcd.print(sound);
      lcd.print("/1023");
      return;
    }
  }
  
  if (millis() - lastLCDUpdate < lcdUpdateInterval) {
    return;
  }
  lastLCDUpdate = millis();
  
  lcd.clear();
  
  lcd.setCursor(0, 0);
  lcd.write(0);
  lcd.print(" ");
  lcd.print(sound);
  lcd.print("/1023 ");
  
  int barLen = map(constrain(percent, 0, 100), 0, 100, 0, 9);
  for (int i = 0; i < 9; i++) {
    if (i < barLen) lcd.print("#");
    else lcd.print(".");
  }
  
  lcd.setCursor(0, 1);
  lcd.print(percent);
  lcd.print("% ");
  
  if (thr > 120) {
    lcd.print("[READING]");
  } else {
    lcd.print("[SILENT]");
  }
  
  lcd.setCursor(0, 2);
  lcd.print("Status: ");
  
  if (isSound) {
    lcd.print("!!! NOISE !!!");
  } else if (sound > thr * 0.6) {
    lcd.print(">> WARNING <<");
  } else {
    lcd.print("   QUIET    ");
  }
  
  lcd.setCursor(0, 3);
  lcd.print("Th:");
  lcd.print(thr);
  lcd.print(" S:x");
  lcd.print(sens, 1);
  lcd.print(" V:");
  lcd.print(violations);
  
  lcd.setCursor(14, 3);
  int db = map(constrain(percent, 0, 100), 0, 100, 30, 100);
  lcd.print(db);
  lcd.print("dB");
}