import os
from pyicloud import PyiCloudService
from pyicloud.exceptions import PyiCloudFailedLoginException, PyiCloudAPIResponseException, PyiCloudServiceNotActivatedException

# グローバル変数としてPyiCloudServiceのインスタンスを保持
api = None

def get_device_location():
    global api
    username = os.environ.get('ICLOUD_USERNAME')
    password = os.environ.get('ICLOUD_PASSWORD')

    if not username or not password:
        return None, None, "ENV_ERROR"

    try:
        api = PyiCloudService(username, password)
        
        if api.requires_2fa:
            return None, None, "2FA_REQUIRED"
        
        for device in api.devices:
            if 'MacBook' in device['name']:
                location = device.location()
                if location:
                    return location['latitude'], location['longitude'], None
        
        return None, None, "NO_LOCATION"

    except PyiCloudFailedLoginException:
        return None, None, "LOGIN_FAILED"
    except (PyiCloudAPIResponseException, PyiCloudServiceNotActivatedException):
        return None, None, "SERVICE_UNAVAILABLE"
    except Exception as e:
        return None, None, f"UNKNOWN_ERROR: {str(e)}"

def verify_2fa_code(verification_code):
    global api
    if api is None:
        return False, "APIが初期化されていません。"

    try:
        result = api.validate_2fa_code(verification_code)
        if result:
            if not api.is_trusted_session:
                api.trust_session()
            return True, None
        return False, "無効な認証コードです。"
    except Exception as e:
        return False, f"2FA検証中にエラーが発生しました: {str(e)}"