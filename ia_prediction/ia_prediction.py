import pandas as pd
import numpy as np
import tensorflow as tf

from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score

# ============================================================
# 1. CHARGEMENT DU DATASET
# ============================================================

data = pd.read_csv("agriculture.csv")

print("Aperçu des données :")
print(data.head())

print("\nInformations :")
print(data.info())

# ============================================================
# 2. ENCODAGE DU TYPE DE CULTURE
# ============================================================

# Transformer le type de culture en variables numériques
data = pd.get_dummies(
    data,
    columns=["type_culture"],
    dtype=int
)

print("\nDataset après encodage :")
print(data.head())

# ============================================================
# 3. SÉPARATION DES ENTRÉES ET DE LA SORTIE
# ============================================================

# rendement = valeur que l'IA doit prédire
X = data.drop("rendement", axis=1)

# Variable cible
y = data["rendement"]

# ============================================================
# 4. DIVISION TRAIN / TEST
# ============================================================

X_train, X_test, y_train, y_test = train_test_split(
    X,
    y,
    test_size=0.20,
    random_state=42
)

print("\nTaille entraînement :", X_train.shape)
print("Taille test :", X_test.shape)

# ============================================================
# 5. NORMALISATION
# ============================================================

scaler = StandardScaler()

X_train = scaler.fit_transform(X_train)
X_test = scaler.transform(X_test)

# ============================================================
# 6. CRÉATION DU MODÈLE TENSORFLOW
# ============================================================

model = tf.keras.Sequential([
    
    tf.keras.layers.Input(shape=(X_train.shape[1],)),

    tf.keras.layers.Dense(64, activation="relu"),

    tf.keras.layers.Dense(32, activation="relu"),

    tf.keras.layers.Dense(16, activation="relu"),

    # Une seule sortie : rendement prédit
    tf.keras.layers.Dense(1)
])

# ============================================================
# 7. COMPILATION
# ============================================================

model.compile(
    optimizer=tf.keras.optimizers.Adam(learning_rate=0.001),
    loss="mse",
    metrics=["mae"]
)

model.summary()

# ============================================================
# 8. ENTRAÎNEMENT
# ============================================================

history = model.fit(
    X_train,
    y_train,
    epochs=200,
    batch_size=16,
    validation_split=0.2,
    verbose=1
)

# ============================================================
# 9. ÉVALUATION
# ============================================================

predictions = model.predict(X_test)

predictions = predictions.flatten()

mae = mean_absolute_error(y_test, predictions)
rmse = np.sqrt(mean_squared_error(y_test, predictions))
r2 = r2_score(y_test, predictions)

print("\n==============================")
print("RÉSULTATS DU MODÈLE")
print("==============================")

print(f"MAE  : {mae:.2f} t/ha")
print(f"RMSE : {rmse:.2f} t/ha")
print(f"R²   : {r2:.2f}")

# ============================================================
# 10. COMPARAISON RÉEL / PRÉDIT
# ============================================================

resultats = pd.DataFrame({
    "Rendement réel": y_test.values,
    "Rendement prédit": predictions
})

print("\nComparaison :")
print(resultats)

# ============================================================
# 11. SAUVEGARDE DU MODÈLE
# ============================================================

model.save("modele_rendement.keras")

# Sauvegarder également le scaler
import joblib

joblib.dump(scaler, "scaler.pkl")

print("\nModèle sauvegardé dans : modele_rendement.keras")
print("Scaler sauvegardé dans : scaler.pkl")