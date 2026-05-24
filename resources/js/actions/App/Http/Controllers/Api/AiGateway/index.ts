import ModelsController from './ModelsController'
import ChatCompletionsController from './ChatCompletionsController'
import EmbeddingsController from './EmbeddingsController'

const AiGateway = {
    ModelsController: Object.assign(ModelsController, ModelsController),
    ChatCompletionsController: Object.assign(ChatCompletionsController, ChatCompletionsController),
    EmbeddingsController: Object.assign(EmbeddingsController, EmbeddingsController),
}

export default AiGateway