import csv
import base64
import json
import os
import sys
from pathlib import Path

os.environ.setdefault("TF_CPP_MIN_LOG_LEVEL", "3")
import tensorflow as tf


DATA_FILE = Path(__file__).with_name("data.csv")
TARGET = "rendement"
NUMERIC_COLUMNS = [
    "Azote",
    "Phosphore",
    "Potassium",
    "temperature",
    "ph",
    "humidite_air",
    "humidite_sol",
    "luminosite",
]
CATEGORICAL_COLUMNS = ["plante", "type_sol"]


def charger_donnees(data_file=DATA_FILE):
    with open(data_file, "r", encoding="utf-8", newline="") as fichier:
        lignes = list(csv.DictReader(fichier, delimiter=";"))
    if not lignes:
        raise ValueError("data.csv est vide.")

    attendues = NUMERIC_COLUMNS + CATEGORICAL_COLUMNS + [TARGET]
    manquantes = [colonne for colonne in attendues if colonne not in lignes[0]]
    if manquantes:
        raise ValueError(f"Colonnes manquantes dans data.csv : {', '.join(manquantes)}")

    entrees = {colonne: [] for colonne in NUMERIC_COLUMNS + CATEGORICAL_COLUMNS}
    cibles = []
    for ligne in lignes:
        for colonne in NUMERIC_COLUMNS:
            entrees[colonne].append(float(ligne[colonne].strip().replace(",", ".")))
        for colonne in CATEGORICAL_COLUMNS:
            entrees[colonne].append(ligne[colonne].strip())
        cibles.append(float(ligne[TARGET].strip().replace(",", ".")))

    return {colonne: tf.constant(valeurs) for colonne, valeurs in entrees.items()}, tf.constant(cibles, dtype=tf.float32)


def construire_modele(entrees):
    couches = []
    entrees_keras = {}

    for colonne in NUMERIC_COLUMNS:
        entree = tf.keras.Input(shape=(1,), name=colonne, dtype=tf.float32)
        normalisation = tf.keras.layers.Normalization(axis=None)
        normalisation.adapt(tf.reshape(entrees[colonne], (-1, 1)))
        couches.append(normalisation(entree))
        entrees_keras[colonne] = entree

    for colonne in CATEGORICAL_COLUMNS:
        entree = tf.keras.Input(shape=(1,), name=colonne, dtype=tf.string)
        encodage = tf.keras.layers.StringLookup(output_mode="one_hot")
        encodage.adapt(entrees[colonne])
        couches.append(encodage(entree))
        entrees_keras[colonne] = entree

    x = tf.keras.layers.Concatenate()(couches)
    x = tf.keras.layers.Dense(32, activation="relu")(x)
    x = tf.keras.layers.Dense(16, activation="relu")(x)
    sortie = tf.keras.layers.Dense(1, name=TARGET)(x)
    modele = tf.keras.Model(inputs=entrees_keras, outputs=sortie)
    modele.compile(optimizer=tf.keras.optimizers.Adam(learning_rate=0.01), loss="mse", metrics=["mae"])
    return modele


def predire_rendement(modele, **parametres):
    entrees = {
        colonne: tf.constant([parametres[colonne]])
        for colonne in NUMERIC_COLUMNS + CATEGORICAL_COLUMNS
    }
    return float(modele.predict(entrees, verbose=0)[0][0])


def executer_prediction(parametres):
    tf.random.set_seed(42)
    entrees, cibles = charger_donnees()
    modele = construire_modele(entrees)
    modele.fit(entrees, cibles, epochs=80, batch_size=8, verbose=0)
    return predire_rendement(modele, **parametres)


if __name__ == "__main__":
    try:
        if len(sys.argv) > 1:
            if sys.argv[1] == "--base64" and len(sys.argv) > 2:
                parametres = json.loads(base64.b64decode(sys.argv[2]).decode("utf-8"))
            else:
                parametres = json.loads(sys.argv[1])
            print(json.dumps({"rendement": executer_prediction(parametres)}, ensure_ascii=False), flush=True)
        else:
            exemple = {
                "plante": "Mais", "Azote": 0.20, "Phosphore": 17, "Potassium": 98,
                "temperature": 27.0, "ph": 5.8, "humidite_air": 78,
                "humidite_sol": 61, "luminosite": 48000, "type_sol": "Sableux",
            }
            prediction = executer_prediction(exemple)
            print(f"Rendement predit : {prediction:.2f} t/ha", flush=True)
    except Exception as erreur:
        print(json.dumps({"erreur": str(erreur)}, ensure_ascii=False), flush=True)
        sys.exit(1)
