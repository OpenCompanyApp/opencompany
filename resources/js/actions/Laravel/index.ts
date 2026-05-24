import Dusk from './Dusk'
import Sanctum from './Sanctum'
import Telescope from './Telescope'

const Laravel = {
    Dusk: Object.assign(Dusk, Dusk),
    Sanctum: Object.assign(Sanctum, Sanctum),
    Telescope: Object.assign(Telescope, Telescope),
}

export default Laravel