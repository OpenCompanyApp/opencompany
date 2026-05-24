import Api from './Api'
import SetupController from './SetupController'
import ProfileController from './ProfileController'
import Auth from './Auth'

const Controllers = {
    Api: Object.assign(Api, Api),
    SetupController: Object.assign(SetupController, SetupController),
    ProfileController: Object.assign(ProfileController, ProfileController),
    Auth: Object.assign(Auth, Auth),
}

export default Controllers