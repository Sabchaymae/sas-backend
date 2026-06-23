import pandas as pd
import numpy as np
from sklearn.ensemble import IsolationForest
from sklearn.neighbors import LocalOutlierFactor
import logging
from datetime import datetime

logger = logging.getLogger(__name__)

class AttendanceAI:
    def __init__(self):
        # Isolation Forest for global anomalies
        self.iso_forest = IsolationForest(contamination=0.1, random_state=42)
        # LOF for local density-based anomalies
        self.lof = LocalOutlierFactor(n_neighbors=20, contamination=0.1)

    def analyze_record(self, record, history):
        """
        Analyze a single attendance record against history
        record: {'clock_in': '08:00', 'clock_out': '17:00', 'date': '2026-06-09'}
        history: list of similar records
        """
        anomalies = []
        
        # 1. Simple Rule-based detection
        clock_in_dt = datetime.strptime(record['clock_in'], "%H:%M:%S")
        if clock_in_dt.hour >= 9:
            anomalies.append({
                'type': 'Late Arrival',
                'description': f"Late arrival at {record['clock_in']}",
                'severity': 'low'
            })
            
        if record.get('clock_out'):
            clock_out_dt = datetime.strptime(record['clock_out'], "%H:%M:%S")
            if clock_out_dt.hour < 16:
                anomalies.append({
                    'type': 'Early Departure',
                    'description': f"Left early at {record['clock_out']}",
                    'severity': 'medium'
                })

        # 2. Machine Learning detection (if history exists)
        if len(history) > 10:
            df = pd.DataFrame(history)
            # Preprocessing
            df['hour'] = pd.to_datetime(df['clock_in']).dt.hour
            df['minute'] = pd.to_datetime(df['clock_in']).dt.minute
            
            X = df[['hour', 'minute']]
            
            # Detect using Isolation Forest
            self.iso_forest.fit(X)
            
            # Current record features
            current_X = np.array([[clock_in_dt.hour, clock_in_dt.minute]])
            prediction = self.iso_forest.predict(current_X)
            
            if prediction[0] == -1:
                anomalies.append({
                    'type': 'Suspect Timing',
                    'description': "Clock-in time is statistically unusual for this employee",
                    'severity': 'high'
                })
                
        return anomalies
