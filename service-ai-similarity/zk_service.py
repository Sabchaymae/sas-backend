import logging

logger = logging.getLogger(__name__)

try:
    from zk import ZK, const
    ZK_AVAILABLE = True
except ImportError:
    ZK_AVAILABLE = False


class ZKService:
    def __init__(self, ip, port=4370):
        self.ip = ip
        self.port = port
        self.zk = None

    def connect(self):
        if not ZK_AVAILABLE:
            logger.error("ZK library not available - cannot connect")
            return None
        try:
            self.zk = ZK(self.ip, port=self.port, timeout=5, force_udp=False)
            return self.zk.connect()
        except Exception as e:
            logger.error(f"Connection to ZK terminal at {self.ip} failed: {e}")
            return None

    def sync_user(self, user_id, name, department):
        if not ZK_AVAILABLE:
            return False
        conn = self.connect()
        if not conn:
            return False
        try:
            # uid must be an integer. If user_id is EMP001, we extract 001.
            try:
                uid = int(''.join(filter(str.isdigit, user_id)))
            except:
                uid = 1
            
            conn.set_user(uid=uid, name=name, privilege=const.USER_DEFAULT, user_id=user_id)
            logger.info(f"Successfully synced user {user_id} to terminal")
            return True
        except Exception as e:
            logger.error(f"Failed to sync user {user_id}: {e}")
            return False
        finally:
            if self.zk and conn:
                self.zk.disconnect()

    def get_attendance_logs(self):
        if not ZK_AVAILABLE:
            return []
        conn = self.connect()
        if not conn:
            return []
        try:
            attendance = conn.get_attendance()
            logs = []
            for entry in attendance:
                logs.append({
                    "user_id": entry.user_id,
                    "timestamp": entry.timestamp.strftime("%Y-%m-%d %H:%M:%S"),
                    "status": entry.status,
                    "punch": entry.punch
                })
            return logs
        except Exception as e:
            logger.error(f"Failed to fetch attendance logs: {e}")
            return []
        finally:
            if self.zk and conn:
                self.zk.disconnect()
