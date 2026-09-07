#include <WiFiS3.h>            
#include <ArduinoHttpClient.h>
#include <DHT.h>

const char* ssid     = "Redmi A5";   //votre nom de réseau 
const char* password = "chanelsteve5";  //votre mot de passe wifi

const char* serverAddress = "10.157.95.81"; //configuration de l'adresse ip du serveur php sur le pc
const int serverPort      = 80;           //configuration du port de connexion 
const String apiPath      = "/st-agro/capteur/insertdata.php"; 

#define DHTPIN 4           // configuration du capteur de temperature et d'humidite de l'air(DHT11) sur la broche D4
#define DHTTYPE DHT11      

#define PIN_RELAIS 3       // configuration du relais sur la broche D3

const int pinSol = A0;     // configuration du capteur d'humidite de sol sur la broche A0
const int pinLumiere = A1; // configuration du capteur de luminosité sur la broche A1
const int pinEau = A2;     // configuration du capteur du niveau contenu dans le sol sur la broche A2

DHT dht(DHTPIN, DHTTYPE);   //initialisation du capteur DHT11
WiFiServer server(80);      //initialisation du serveur web sur le port 80 pour l'application
// initialisation du client pour l'envoi vers la base de donnée du pc
WiFiClient wifiClientData;  
HttpClient httpClient = HttpClient(wifiClientData, serverAddress, serverPort);

unsigned long lastLogTime = 0;
const unsigned long logInterval = 10000; //Envoi automatique à la base de données toutes les 10 secondes

void setup() {
  Serial.begin(9600); //initialisation de la vitesse de transmition des informations en bit par second
  //configuration de la broche du relais en sortie
  pinMode(PIN_RELAIS, OUTPUT);
  digitalWrite(PIN_RELAIS, LOW); //pompe eteinte par defaut au démarrage 
  dht.begin(); //initialisation du capteur DHT11

  //connexion au reseau wifi
  Serial.print("Connexion au Wi-Fi : ");
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWi-Fi connecté !");
  server.begin();
  Serial.print("Adresse IP : ");
  Serial.println(WiFi.localIP()); 
}

void loop() {
  // 1. ÉCOUTE DE L'APPLICATION (Inchangé)
  WiFiClient appClient = server.available();
  if (appClient) {
    String currentLine = "";
    while (appClient.connected()) {
      if (appClient.available()) {
        char c = appClient.read();
        if (c == '\n') {
          if (currentLine.length() == 0) {
            //envoi d'une reponse HTTP standard de succès a l'application 
            appClient.println("HTTP/1.1 200 OK");
            appClient.println("Content-Type: text/html");
            appClient.println("Connection: close");
            appClient.println();
            appClient.println("<!DOCTYPE HTML><html><body><h1>Pompe Commandee</h1></body></html>");
            break;
          } else { currentLine = ""; }
        } else if (c != '\r') { currentLine += c; }

        if (currentLine.endsWith("GET /POMPE=ON"))  digitalWrite(PIN_RELAIS, HIGH);
        if (currentLine.endsWith("GET /POMPE=OFF")) digitalWrite(PIN_RELAIS, LOW);
      }
    }
    delay(1);
    appClient.stop();
  }

  // 2. ENVOI DES PARAMÈTRES (AJOUT DE L'HUMIDITÉ DE L'AIR)
  if (millis() - lastLogTime >= logInterval) {
    lastLogTime = millis();

    // LECTURE DU DHT11 (Les deux valeurs sont lues ici)
    float temperature  = dht.readTemperature(); 
    float humiditeAir  = dht.readHumidity();    // <--- AJOUT : Humidité de l'air lue ici !

    int humiditeSol    = analogRead(pinSol);     
    int luminosite     = analogRead(pinLumiere); 
    int niveauEau      = analogRead(pinEau);     

    if (isnan(temperature)) temperature = 0.0;
    if (isnan(humiditeAir)) humiditeAir = 0.0;  

    // Affichage des mesures dans le moniteur série pour verification local
    Serial.println("\n--- Lecture des Capteurs ---");
    Serial.print("Température Air : "); Serial.print(temperature); Serial.println(" °C");
    Serial.print("Humidité de l'Air : "); Serial.print(humiditeAir); Serial.println(" %"); // <--- Affichage
    Serial.print("Humidité Terre : "); Serial.println(humiditeSol);
    Serial.print("Luminosité      : "); Serial.println(luminosite);
    Serial.print("Niveau d'Eau    : "); Serial.println(niveauEau);

    //Envoi des informations structurées en JSON au pc via le wifi
    if (WiFi.status() == WL_CONNECTED) {
      String jsonPayload = "{";
      jsonPayload += "\"temperature\":" + String(temperature) + ",";
      jsonPayload += "\"humidite_air\":" + String(humiditeAir) + ","; // <--- Envoi au PHP
      jsonPayload += "\"humidite_sol\":" + String(humiditeSol) + ",";
      jsonPayload += "\"luminosite\":" + String(luminosite) + ",";
      jsonPayload += "\"niveau_eau\":" + String(niveauEau);
      jsonPayload += "}";

      httpClient.beginRequest();
      httpClient.post(apiPath);
      httpClient.sendHeader("Content-Type", "application/json");
      httpClient.sendHeader("Content-Length", jsonPayload.length());
      httpClient.beginBody();
      httpClient.print(jsonPayload);
      httpClient.endRequest();

      int statusCode = httpClient.responseStatusCode();
      Serial.print("Statut BDD reçu : ");
      Serial.println(statusCode); 
    }else{
      Serial.println("Erreur Wi-Fi: Deconnecte au réseau : '");
      Serial.print (ssid);
      Serial.println("'");
  }
}
}