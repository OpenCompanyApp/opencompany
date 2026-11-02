import Dusk from './Dusk'
import Sanctum from './Sanctum'

const Laravel = {
    Dusk: Object.assign(Dusk, Dusk),
    Sanctum: Object.assign(Sanctum, Sanctum),
}

export default Laravel